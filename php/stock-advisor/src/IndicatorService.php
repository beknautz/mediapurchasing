<?php
/**
 * src/IndicatorService.php
 * Computes technical indicators from price_history and stores them in
 * the indicators table.
 *
 * Indicators calculated:
 *   - RSI(14)          — Relative Strength Index
 *   - MACD(12,26,9)    — MACD line, signal line, histogram
 *   - SMA(20), SMA(50) — Simple Moving Averages
 *   - Bollinger Bands  — 20-period SMA ± 2 std-dev
 */
class IndicatorService extends BaseService
{
    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Calculate all indicators for a stock using its stored price history,
     * then upsert the latest row into the indicators table.
     *
     * @param int    $stockId  Primary key in stocks table
     * @param string $symbol   Used only for logging / exceptions
     * @param string $forDate  Date to store the indicators against (YYYY-MM-DD)
     *
     * @throws RuntimeException when there is insufficient price history
     */
    public function calculateAndStore(int $stockId, string $symbol, string $forDate): void
    {
        // We need at least 60 data points for SMA50 + some headroom
        $closes = $this->getClosesSince($stockId, 60);

        if (count($closes) < 27) {
            throw new RuntimeException(
                "Insufficient price history for {$symbol}: need ≥27 days, have " . count($closes)
            );
        }

        $rsi     = $this->rsi($closes, 14);
        $macd    = $this->macd($closes);
        $sma20   = $this->sma($closes, 20);
        $sma50   = count($closes) >= 50 ? $this->sma($closes, 50) : null;
        $bb      = $this->bollingerBands($closes, 20);

        $this->upsertIndicators($stockId, $forDate, [
            'rsi14'       => $rsi,
            'macd_line'   => $macd['line'],
            'signal_line' => $macd['signal'],
            'macd_hist'   => $macd['hist'],
            'sma20'       => $sma20,
            'sma50'       => $sma50,
            'bb_upper'    => $bb['upper'],
            'bb_middle'   => $bb['middle'],
            'bb_lower'    => $bb['lower'],
        ]);
    }

    /**
     * Returns the most recently stored indicator row for a stock, or [].
     */
    public function getLatest(int $stockId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM indicators WHERE stock_id = :sid ORDER BY trade_date DESC LIMIT 1'
        );
        $stmt->execute([':sid' => $stockId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Returns the last $limit indicator rows for a stock (newest first).
     */
    public function getHistory(int $stockId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM indicators WHERE stock_id = :sid ORDER BY trade_date DESC LIMIT :lim'
        );
        $stmt->bindValue(':sid', $stockId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // Indicator math
    // -----------------------------------------------------------------------

    /**
     * RSI — Wilder's smoothing method.
     *
     * @param float[] $closes  Prices in ascending chronological order
     * @param int     $period  Lookback period (default 14)
     * @return float|null  RSI value 0–100, or null if not enough data
     */
    public function rsi(array $closes, int $period = 14): ?float
    {
        if (count($closes) < $period + 1) {
            return null;
        }

        // Compute initial average gain/loss over first $period price changes
        $gains  = 0.0;
        $losses = 0.0;

        for ($i = 1; $i <= $period; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            if ($change > 0) {
                $gains  += $change;
            } else {
                $losses += abs($change);
            }
        }

        $avgGain = $gains  / $period;
        $avgLoss = $losses / $period;

        // Wilder smoothing for remaining data points
        $n = count($closes);
        for ($i = $period + 1; $i < $n; $i++) {
            $change  = $closes[$i] - $closes[$i - 1];
            $gain    = $change > 0 ? $change : 0.0;
            $loss    = $change < 0 ? abs($change) : 0.0;
            $avgGain = ($avgGain * ($period - 1) + $gain)  / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $loss)  / $period;
        }

        if ($avgLoss == 0.0) {
            return 100.0;
        }

        $rs = $avgGain / $avgLoss;
        return round(100 - (100 / (1 + $rs)), 4);
    }

    /**
     * MACD(12, 26, 9).
     *
     * @param float[] $closes  Ascending chronological prices
     * @return array  ['line'=>float|null, 'signal'=>float|null, 'hist'=>float|null]
     */
    public function macd(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): array
    {
        $null = ['line' => null, 'signal' => null, 'hist' => null];

        if (count($closes) < $slow + $signal) {
            return $null;
        }

        $ema12 = $this->emaArray($closes, $fast);
        $ema26 = $this->emaArray($closes, $slow);

        // MACD line = EMA12 − EMA26 (align on the same indices)
        $offset    = $slow - $fast; // ema26 starts $offset elements later
        $macdLine  = [];

        $n26 = count($ema26);
        for ($i = 0; $i < $n26; $i++) {
            $macdLine[] = $ema12[$i + $offset] - $ema26[$i];
        }

        if (count($macdLine) < $signal) {
            return $null;
        }

        $signalLine = $this->emaArray($macdLine, $signal);
        $last       = count($signalLine) - 1;
        $macdLast   = $macdLine[count($macdLine) - 1];
        $sigLast    = $signalLine[$last];

        return [
            'line'   => round($macdLast, 6),
            'signal' => round($sigLast,  6),
            'hist'   => round($macdLast - $sigLast, 6),
        ];
    }

    /**
     * Simple Moving Average of the last $period values.
     *
     * @param float[] $closes  Ascending prices
     * @return float|null
     */
    public function sma(array $closes, int $period = 20): ?float
    {
        if (count($closes) < $period) {
            return null;
        }

        $slice = array_slice($closes, -$period);
        return round(array_sum($slice) / $period, 4);
    }

    /**
     * Bollinger Bands (20-period SMA ± 2 standard deviations).
     *
     * @param float[] $closes  Ascending prices
     * @return array  ['upper'=>float|null, 'middle'=>float|null, 'lower'=>float|null]
     */
    public function bollingerBands(array $closes, int $period = 20): array
    {
        $null = ['upper' => null, 'middle' => null, 'lower' => null];

        if (count($closes) < $period) {
            return $null;
        }

        $slice  = array_slice($closes, -$period);
        $mean   = array_sum($slice) / $period;
        $sumSq  = 0.0;

        foreach ($slice as $v) {
            $sumSq += ($v - $mean) ** 2;
        }

        $stddev = sqrt($sumSq / $period);

        return [
            'upper'  => round($mean + 2 * $stddev, 4),
            'middle' => round($mean, 4),
            'lower'  => round($mean - 2 * $stddev, 4),
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Exponential Moving Average array (all values, not just the last).
     * Uses simple average as the seed value.
     *
     * @param float[] $values  Input series (ascending)
     * @param int     $period
     * @return float[]
     */
    private function emaArray(array $values, int $period): array
    {
        if (count($values) < $period) {
            return [];
        }

        $k      = 2 / ($period + 1);
        $result = [];

        // Seed with SMA of the first $period values
        $seed   = array_sum(array_slice($values, 0, $period)) / $period;
        $result[] = $seed;

        $n = count($values);
        for ($i = $period; $i < $n; $i++) {
            $prev     = end($result);
            $result[] = $values[$i] * $k + $prev * (1 - $k);
        }

        return $result;
    }

    /**
     * Load the last $limit close prices for a stock from price_history,
     * returned ascending (oldest first).
     *
     * @return float[]
     */
    private function getClosesSince(int $stockId, int $limit = 60): array
    {
        $stmt = $this->db->prepare(
            'SELECT close FROM price_history
              WHERE stock_id = :sid
              ORDER BY trade_date DESC
              LIMIT :lim'
        );
        $stmt->bindValue(':sid', $stockId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,   PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // Reverse so oldest is first
        return array_reverse(array_map('floatval', $rows));
    }

    /** Upsert a single indicators row for (stock_id, trade_date). */
    private function upsertIndicators(int $stockId, string $date, array $vals): void
    {
        $sql = 'INSERT INTO indicators
                    (stock_id, trade_date, rsi14, macd_line, signal_line,
                     macd_hist, sma20, sma50, bb_upper, bb_middle, bb_lower)
                VALUES
                    (:sid, :dt, :rsi, :ml, :sl, :mh, :s20, :s50, :bbu, :bbm, :bbl)
                ON DUPLICATE KEY UPDATE
                    rsi14       = VALUES(rsi14),
                    macd_line   = VALUES(macd_line),
                    signal_line = VALUES(signal_line),
                    macd_hist   = VALUES(macd_hist),
                    sma20       = VALUES(sma20),
                    sma50       = VALUES(sma50),
                    bb_upper    = VALUES(bb_upper),
                    bb_middle   = VALUES(bb_middle),
                    bb_lower    = VALUES(bb_lower)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sid' => $stockId,
            ':dt'  => $date,
            ':rsi' => $vals['rsi14'],
            ':ml'  => $vals['macd_line'],
            ':sl'  => $vals['signal_line'],
            ':mh'  => $vals['macd_hist'],
            ':s20' => $vals['sma20'],
            ':s50' => $vals['sma50'],
            ':bbu' => $vals['bb_upper'],
            ':bbm' => $vals['bb_middle'],
            ':bbl' => $vals['bb_lower'],
        ]);
    }
}
