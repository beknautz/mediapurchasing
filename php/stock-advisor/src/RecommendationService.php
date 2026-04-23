<?php
/**
 * src/RecommendationService.php
 * Combines technical indicators into a 0-100 confidence score and
 * generates a BUY / SELL / HOLD recommendation.
 *
 * Scoring model (all signals sum from a base of 50):
 *
 *   RSI(14)         ±25 pts   oversold <30 → +25, overbought >70 → -25
 *   MACD histogram  ±20 pts   positive rising → +20, negative falling → -20
 *   SMA trend       ±15 pts   price > SMA20 > SMA50 → +15 etc.
 *   Bollinger Bands ±15 pts   below lower band → +15, above upper → -15
 *
 * Thresholds:
 *   confidence ≥ 65  →  BUY
 *   confidence ≤ 35  →  SELL
 *   otherwise        →  HOLD
 */
class RecommendationService extends BaseService
{
    private const BUY_THRESHOLD  = 65;
    private const SELL_THRESHOLD = 35;

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Generate and persist a recommendation for a single stock.
     *
     * Reads the latest indicator row for the stock, computes a confidence
     * score, derives BUY/SELL/HOLD, and upserts into recommendations.
     *
     * @param int    $stockId
     * @param string $recDate   YYYY-MM-DD
     * @param float  $closePrice  Latest closing price
     *
     * @return array  ['action'=>string, 'confidence'=>int, 'notes'=>string]
     */
    public function generateAndStore(int $stockId, string $recDate, float $closePrice): array
    {
        $indSvc     = new IndicatorService();
        $indicators = $indSvc->getLatest($stockId);

        [$score, $notes] = $this->score($indicators, $closePrice);
        $score  = max(0, min(100, (int) round($score)));
        $action = $this->action($score);

        $this->upsertRecommendation($stockId, $recDate, $action, $score, $closePrice, $notes);

        return [
            'action'     => $action,
            'confidence' => $score,
            'notes'      => $notes,
        ];
    }

    /**
     * Returns today's recommendation rows joined with stock info.
     * If $date is omitted the most recent recommendation date is used.
     */
    public function getTodaysRecommendations(string $date = ''): array
    {
        if ($date === '') {
            $row = $this->db->query(
                'SELECT MAX(rec_date) AS d FROM recommendations'
            )->fetch(PDO::FETCH_ASSOC);
            $date = $row['d'] ?? date('Y-m-d');
        }

        $stmt = $this->db->prepare(
            'SELECT r.*, s.symbol, s.name, s.asset_type, s.sector
               FROM recommendations r
               JOIN stocks s ON s.id = r.stock_id
              WHERE r.rec_date = :d
                AND s.active   = 1
              ORDER BY r.confidence DESC, s.symbol'
        );
        $stmt->execute([':d' => $date]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the recommendation history for one stock (newest first).
     */
    public function getHistory(int $stockId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM recommendations WHERE stock_id = :sid ORDER BY rec_date DESC LIMIT :lim'
        );
        $stmt->bindValue(':sid', $stockId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the latest single recommendation row for a stock, or [].
     */
    public function getLatest(int $stockId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, s.symbol, s.name
               FROM recommendations r
               JOIN stocks s ON s.id = r.stock_id
              WHERE r.stock_id = :sid
              ORDER BY r.rec_date DESC LIMIT 1'
        );
        $stmt->execute([':sid' => $stockId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // -----------------------------------------------------------------------
    // Scoring engine
    // -----------------------------------------------------------------------

    /**
     * Compute a composite confidence score 0–100.
     *
     * @param  array $ind        Row from indicators table (may be empty / null values)
     * @param  float $close      Today's closing price
     * @return array [float $score, string $notes]
     */
    private function score(array $ind, float $close): array
    {
        $score = 50.0;
        $parts = [];

        // ── RSI(14): ±25 points ─────────────────────────────────────────────
        if (isset($ind['rsi14']) && $ind['rsi14'] !== null) {
            $rsi   = (float) $ind['rsi14'];
            $delta = 0.0;

            if ($rsi < 20) {
                $delta = 25;
            } elseif ($rsi < 30) {
                $delta = 20;
            } elseif ($rsi < 40) {
                $delta = 10;
            } elseif ($rsi > 80) {
                $delta = -25;
            } elseif ($rsi > 70) {
                $delta = -20;
            } elseif ($rsi > 60) {
                $delta = -10;
            }

            $score += $delta;
            $parts[] = sprintf('RSI %.1f (%+.0f)', $rsi, $delta);
        }

        // ── MACD histogram: ±20 points ───────────────────────────────────────
        if (isset($ind['macd_hist']) && $ind['macd_hist'] !== null) {
            $hist  = (float) $ind['macd_hist'];
            $line  = (float) ($ind['macd_line']   ?? 0);
            $sig   = (float) ($ind['signal_line'] ?? 0);
            $delta = 0.0;

            if ($hist > 0 && $line > $sig) {       // bullish momentum
                $delta = $hist > abs($line) * 0.1 ? 20 : 10;
            } elseif ($hist < 0 && $line < $sig) { // bearish momentum
                $delta = $hist < -abs($line) * 0.1 ? -20 : -10;
            }

            $score += $delta;
            $parts[] = sprintf('MACD hist %.4f (%+.0f)', $hist, $delta);
        }

        // ── SMA trend: ±15 points ────────────────────────────────────────────
        $sma20 = isset($ind['sma20']) ? (float) $ind['sma20'] : null;
        $sma50 = isset($ind['sma50']) ? (float) $ind['sma50'] : null;
        if ($sma20 !== null) {
            $delta = 0.0;

            if ($close > $sma20) {
                $delta += 5;
            } else {
                $delta -= 5;
            }

            if ($sma50 !== null) {
                if ($sma20 > $sma50 && $close > $sma20) {
                    $delta += 10; // full uptrend
                } elseif ($sma20 < $sma50 && $close < $sma20) {
                    $delta -= 10; // full downtrend
                }
            }

            $score += $delta;
            $parts[] = sprintf(
                'SMA20 %.2f / SMA50 %s (%+.0f)',
                $sma20,
                $sma50 !== null ? number_format($sma50, 2) : 'N/A',
                $delta
            );
        }

        // ── Bollinger Bands: ±15 points ──────────────────────────────────────
        $bbLower = isset($ind['bb_lower']) ? (float) $ind['bb_lower'] : null;
        $bbUpper = isset($ind['bb_upper']) ? (float) $ind['bb_upper'] : null;
        if ($bbLower !== null && $bbUpper !== null) {
            $delta     = 0.0;
            $bbMiddle  = (float) ($ind['bb_middle'] ?? (($bbUpper + $bbLower) / 2));
            $bandwidth = $bbUpper - $bbLower;
            $pct       = $bandwidth > 0 ? ($close - $bbLower) / $bandwidth : 0.5;

            if ($close <= $bbLower) {
                $delta = 15;        // oversold below lower band
            } elseif ($pct < 0.15) {
                $delta = 8;         // near lower band
            } elseif ($close >= $bbUpper) {
                $delta = -15;       // overbought above upper band
            } elseif ($pct > 0.85) {
                $delta = -8;        // near upper band
            }

            $score += $delta;
            $parts[] = sprintf(
                'BB [%.2f–%.2f], close %.2f (%+.0f)',
                $bbLower, $bbUpper, $close, $delta
            );
        }

        return [$score, implode('; ', $parts)];
    }

    /** Derive BUY/SELL/HOLD from the confidence score. */
    private function action(int $score): string
    {
        if ($score >= self::BUY_THRESHOLD) {
            return 'BUY';
        }
        if ($score <= self::SELL_THRESHOLD) {
            return 'SELL';
        }
        return 'HOLD';
    }

    // -----------------------------------------------------------------------
    // Persistence
    // -----------------------------------------------------------------------

    private function upsertRecommendation(
        int    $stockId,
        string $date,
        string $action,
        int    $confidence,
        float  $closePrice,
        string $notes
    ): void {
        $sql = 'INSERT INTO recommendations
                    (stock_id, rec_date, action, confidence, close_price, notes)
                VALUES
                    (:sid, :dt, :act, :conf, :cp, :notes)
                ON DUPLICATE KEY UPDATE
                    action      = VALUES(action),
                    confidence  = VALUES(confidence),
                    close_price = VALUES(close_price),
                    notes       = VALUES(notes)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sid'   => $stockId,
            ':dt'    => $date,
            ':act'   => $action,
            ':conf'  => $confidence,
            ':cp'    => $closePrice,
            ':notes' => $notes,
        ]);
    }
}
