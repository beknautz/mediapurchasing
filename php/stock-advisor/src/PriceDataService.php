<?php
/**
 * src/PriceDataService.php
 * Fetches price candles from Schwab and stores them in price_history.
 * Also provides read helpers for the indicator and recommendation pipelines.
 */
class PriceDataService extends BaseService
{
    private SchwabApiService $schwab;

    public function __construct()
    {
        parent::__construct();
        $this->schwab = new SchwabApiService();
    }

    // -----------------------------------------------------------------------
    // Watchlist management
    // -----------------------------------------------------------------------

    /** Returns all active stocks ordered by symbol. */
    public function getActiveStocks(): array
    {
        return $this->db
            ->query('SELECT * FROM stocks WHERE active = 1 ORDER BY symbol')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Returns all stocks (active and inactive) ordered by symbol. */
    public function getAllStocks(): array
    {
        return $this->db
            ->query('SELECT * FROM stocks ORDER BY symbol')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Returns a single stock row by symbol, or [] if not found. */
    public function getStockBySymbol(string $symbol): array
    {
        $stmt = $this->db->prepare('SELECT * FROM stocks WHERE symbol = :s LIMIT 1');
        $stmt->execute([':s' => strtoupper($symbol)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Returns a single stock row by id, or [] if not found. */
    public function getStockById(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM stocks WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Add a symbol to the watchlist.
     * If it already exists (even if inactive) it is re-activated.
     *
     * @throws RuntimeException if the symbol is invalid / not found on Schwab
     */
    public function addStock(string $symbol, string $name = '', string $assetType = 'stock', string $sector = ''): int
    {
        $symbol = strtoupper(trim($symbol));

        // Check existing
        $existing = $this->getStockBySymbol($symbol);
        if (!empty($existing)) {
            $stmt = $this->db->prepare(
                'UPDATE stocks SET active = 1, name = :n, asset_type = :t, sector = :sec WHERE id = :id'
            );
            $stmt->execute([
                ':n'   => $name    ?: $existing['name'],
                ':t'   => $assetType,
                ':sec' => $sector,
                ':id'  => $existing['id'],
            ]);
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO stocks (symbol, name, asset_type, sector) VALUES (:s, :n, :t, :sec)'
        );
        $stmt->execute([
            ':s'   => $symbol,
            ':n'   => $name,
            ':t'   => $assetType,
            ':sec' => $sector,
        ]);

        return $this->lastInsertId();
    }

    /** Soft-delete: mark a stock inactive (preserves history). */
    public function removeStock(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE stocks SET active = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    // -----------------------------------------------------------------------
    // Price fetching and storage
    // -----------------------------------------------------------------------

    /**
     * Fetch up to $days of daily price history from Schwab for the given
     * stock and upsert every candle into price_history.
     *
     * Returns the number of rows inserted or updated.
     */
    public function syncPriceHistory(int $stockId, string $symbol, int $days = 90): int
    {
        $candles = $this->schwab->getPriceHistory($symbol, $days);

        if (empty($candles)) {
            return 0;
        }

        $sql = 'INSERT INTO price_history
                    (stock_id, trade_date, open, high, low, close, volume)
                VALUES
                    (:sid, :dt, :o, :h, :l, :c, :v)
                ON DUPLICATE KEY UPDATE
                    open   = VALUES(open),
                    high   = VALUES(high),
                    low    = VALUES(low),
                    close  = VALUES(close),
                    volume = VALUES(volume)';

        $stmt  = $this->db->prepare($sql);
        $count = 0;

        foreach ($candles as $candle) {
            $stmt->execute([
                ':sid' => $stockId,
                ':dt'  => $candle['date'],
                ':o'   => $candle['open'],
                ':h'   => $candle['high'],
                ':l'   => $candle['low'],
                ':c'   => $candle['close'],
                ':v'   => $candle['volume'],
            ]);
            $count++;
        }

        return $count;
    }

    // -----------------------------------------------------------------------
    // Price history read helpers
    // -----------------------------------------------------------------------

    /**
     * Returns the most recent $limit daily close prices for a stock,
     * oldest first (ascending by trade_date).
     *
     * @return float[]
     */
    public function getRecentCloses(int $stockId, int $limit = 60): array
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

        $closes = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'close');
        // Reverse so oldest is first
        return array_reverse(array_map('floatval', $closes));
    }

    /**
     * Returns a paginated price history for a given stock (newest first).
     */
    public function getPriceHistory(int $stockId, int $page = 1, int $pageSize = 30): array
    {
        $sql = 'SELECT * FROM price_history WHERE stock_id = :sid ORDER BY trade_date DESC';
        return $this->paginate($sql, [':sid' => $stockId], $page, $pageSize);
    }

    /**
     * Returns the most recent single candle row for a stock, or [].
     */
    public function getLatestCandle(int $stockId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM price_history WHERE stock_id = :sid ORDER BY trade_date DESC LIMIT 1'
        );
        $stmt->execute([':sid' => $stockId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
