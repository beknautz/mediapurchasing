<?php
/**
 * src/AiVideoCostService.php
 * AI Video Studio — cost tracking and reporting service.
 */

class AiVideoCostService extends BaseService
{
    // Claude cost rates per 1M tokens
    private static array $RATES = [
        'claude-opus-4-5'    => ['input' => 15.00, 'output' => 75.00],
        'claude-sonnet-4-5'  => ['input' =>  3.00, 'output' => 15.00],
        'claude-haiku-3'     => ['input' =>  0.25, 'output' =>  1.25],
    ];

    // -----------------------------------------------------------------------
    // logClaudeCost()
    // Inserts a cost row for a Claude API call. Returns the total cost.
    // -----------------------------------------------------------------------
    public function logClaudeCost(
        int    $campaignId,
        int    $jobId,
        string $costType,
        string $model,
        int    $inputTokens,
        int    $outputTokens
    ): float {
        $rates      = self::$RATES[$model] ?? self::$RATES['claude-sonnet-4-5'];
        $inputCost  = ($inputTokens  / 1_000_000) * $rates['input'];
        $outputCost = ($outputTokens / 1_000_000) * $rates['output'];
        $total      = round($inputCost + $outputCost, 6);
        $totalUnits = $inputTokens + $outputTokens;
        $unitCost   = $totalUnits > 0 ? round($total / $totalUnits, 8) : 0;

        $stmt = $this->db->prepare(
            'INSERT INTO ai_video_costs
                (campaign_id, job_id, cost_type, provider, model,
                 units, unit_cost, total_cost, notes, created_at)
             VALUES
                (:campaign_id, :job_id, :cost_type, "anthropic", :model,
                 :units, :unit_cost, :total_cost, :notes, NOW())'
        );
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':job_id'      => $jobId ?: null,
            ':cost_type'   => $costType,
            ':model'       => $model,
            ':units'       => $totalUnits,
            ':unit_cost'   => $unitCost,
            ':total_cost'  => $total,
            ':notes'       => sprintf('%s — input %d + output %d tokens', $costType, $inputTokens, $outputTokens),
        ]);

        return $total;
    }

    // -----------------------------------------------------------------------
    // logProviderCost()
    // Inserts a cost row for a provider (Veo, etc.) call.
    // -----------------------------------------------------------------------
    public function logProviderCost(
        int    $campaignId,
        int    $jobId,
        float  $cost,
        string $provider,
        string $notes = ''
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO ai_video_costs
                (campaign_id, job_id, cost_type, provider, model,
                 units, unit_cost, total_cost, notes, created_at)
             VALUES
                (:campaign_id, :job_id, "video_generation", :provider, :provider,
                 1, :cost, :cost, :notes, NOW())'
        );
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':job_id'      => $jobId ?: null,
            ':provider'    => $provider,
            ':cost'        => $cost,
            ':notes'       => $notes,
        ]);
    }

    // -----------------------------------------------------------------------
    // calculateCampaignTotal()
    // Returns aggregated costs for a campaign.
    // -----------------------------------------------------------------------
    public function calculateCampaignTotal(int $campaignId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                SUM(CASE WHEN provider = "anthropic" THEN total_cost ELSE 0 END) AS claude_cost,
                SUM(CASE WHEN provider != "anthropic" THEN total_cost ELSE 0 END) AS provider_cost,
                SUM(total_cost) AS total
               FROM ai_video_costs WHERE campaign_id = :cid'
        );
        $stmt->execute([':cid' => $campaignId]);
        $row = $stmt->fetch();

        // Count revisions
        $revStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ai_video_reviews
              WHERE campaign_id = :cid AND review_status = "revision_requested"'
        );
        $revStmt->execute([':cid' => $campaignId]);
        $revCount = (int)$revStmt->fetchColumn();

        return [
            'claude_cost'    => round((float)($row['claude_cost']   ?? 0), 4),
            'provider_cost'  => round((float)($row['provider_cost'] ?? 0), 4),
            'total'          => round((float)($row['total']         ?? 0), 4),
            'revision_count' => $revCount,
        ];
    }

    // -----------------------------------------------------------------------
    // calculateJobTotal()
    // Returns total cost for a single job.
    // -----------------------------------------------------------------------
    public function calculateJobTotal(int $jobId): float
    {
        $stmt = $this->db->prepare(
            'SELECT SUM(total_cost) FROM ai_video_costs WHERE job_id = :jid'
        );
        $stmt->execute([':jid' => $jobId]);
        return round((float)($stmt->fetchColumn() ?? 0), 4);
    }

    // -----------------------------------------------------------------------
    // getCostSummary()
    // Returns billing summary with markup applied.
    // -----------------------------------------------------------------------
    public function getCostSummary(int $campaignId, float $markupPct = 30.0): array
    {
        $totals       = $this->calculateCampaignTotal($campaignId);
        $internalCost = $totals['total'];
        $markupAmt    = round($internalCost * ($markupPct / 100), 4);
        $billableFee  = round($internalCost + $markupAmt, 4);
        $margin       = $billableFee > 0 ? round(($markupAmt / $billableFee) * 100, 2) : 0.0;

        return [
            'internal_cost' => $internalCost,
            'markup_pct'    => $markupPct,
            'markup_amount' => $markupAmt,
            'billable_fee'  => $billableFee,
            'margin'        => $margin,
            'claude_cost'   => $totals['claude_cost'],
            'provider_cost' => $totals['provider_cost'],
            'revision_count'=> $totals['revision_count'],
        ];
    }

    // -----------------------------------------------------------------------
    // getCostRows()
    // Returns all cost rows for a campaign with job info.
    // -----------------------------------------------------------------------
    public function getCostRows(int $campaignId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, j.job_status, j.provider AS job_provider
               FROM ai_video_costs c
               LEFT JOIN ai_video_jobs j ON j.id = c.job_id
              WHERE c.campaign_id = :cid
              ORDER BY c.created_at DESC'
        );
        $stmt->execute([':cid' => $campaignId]);
        return $stmt->fetchAll();
    }
}
