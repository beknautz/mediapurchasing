<?php
/**
 * src/BudgetPlannerService.php
 * Media Buying Platform — AI-powered budget proposal service
 */

class BudgetPlannerService extends BaseService
{
    private const API_URL     = 'https://api.anthropic.com/v1/messages';
    private const API_MODEL   = 'claude-opus-4-7';
    private const API_VERSION = '2023-06-01';

    // -----------------------------------------------------------------------
    // generate()
    // Calls Claude API with event details and vendor data, returns structured
    // Good/Better/Best allocation.
    //
    // $params keys: title, event_description, event_demographics,
    //               budget_good, budget_better, budget_best
    //
    // Returns: ['success'=>bool, 'data'=>array, 'vendors'=>array, 'message'=>string]
    // -----------------------------------------------------------------------
    public function generate(array $params): array
    {
        $apiKey = $this->getSetting('anthropic_api_key');
        if ($apiKey === '') {
            return [
                'success' => false,
                'message' => 'Anthropic API key not configured. Go to Admin → Workflow Settings and add your key.',
            ];
        }

        $vendors = $this->getActiveVendorsForAI();
        if (empty($vendors)) {
            return [
                'success' => false,
                'message' => 'No active vendors found. Add vendors with Demographics and Media Kit info first.',
            ];
        }

        $vendorLines = [];
        foreach ($vendors as $v) {
            $line = '- ID: ' . $v['id']
                  . ' | Name: ' . $v['company_name']
                  . ' | Category: ' . ($v['media_category'] ?: 'Uncategorized');
            if (!empty($v['demographics'])) {
                $line .= "\n  Audience Demographics: " . $v['demographics'];
            }
            if (!empty($v['media_kit'])) {
                $line .= "\n  Media Kit / Reach: " . $v['media_kit'];
            }
            $vendorLines[] = $line;
        }
        $vendorBlock = implode("\n", $vendorLines);

        $budgetGood   = number_format((float)($params['budget_good']   ?? 0), 2);
        $budgetBetter = number_format((float)($params['budget_better'] ?? 0), 2);
        $budgetBest   = number_format((float)($params['budget_best']   ?? 0), 2);

        $prompt = "You are an expert media buyer specializing in advertising campaigns for events.\n\n"
            . "EVENT DETAILS:\n"
            . "Title: " . ($params['title'] ?? 'Event') . "\n"
            . "Description: " . ($params['event_description'] ?? '') . "\n"
            . "Target Demographics: " . ($params['event_demographics'] ?? '') . "\n\n"
            . "BUDGET TIERS:\n"
            . "- Good:   \${$budgetGood} total\n"
            . "- Better: \${$budgetBetter} total\n"
            . "- Best:   \${$budgetBest} total\n\n"
            . "AVAILABLE VENDORS:\n"
            . $vendorBlock . "\n\n"
            . "TASK:\n"
            . "Allocate the advertising budget across the vendors whose audience demographics and media reach\n"
            . "best match the event and its target demographics. Follow these rules:\n"
            . "1. For each tier (Good, Better, Best), the allocated amounts MUST sum exactly to that tier's total.\n"
            . "2. Higher tiers may include more vendors or larger individual allocations.\n"
            . "3. Only include vendors that are a good fit — not all vendors need to appear in every tier.\n"
            . "4. Use realistic media buying amounts (minimum \$500 per vendor per tier).\n"
            . "5. Group vendors by their category for a balanced media mix.\n\n"
            . "Respond ONLY with a valid JSON object — no markdown fences, no explanation outside the JSON:\n"
            . "{\n"
            . "  \"rationale\": \"2-3 sentence strategy overview\",\n"
            . "  \"allocations\": {\n"
            . "    \"good\": [\n"
            . "      {\"vendor_id\": 1, \"vendor_name\": \"Company\", \"category\": \"TV - Spanish\","
            . " \"amount\": 5000, \"rationale\": \"One sentence why\"}\n"
            . "    ],\n"
            . "    \"better\": [ ... ],\n"
            . "    \"best\":   [ ... ]\n"
            . "  }\n"
            . "}";

        $payload = [
            'model'      => self::API_MODEL,
            'max_tokens' => 4096,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            return ['success' => false, 'message' => 'API connection error: ' . $curlErr];
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200) {
            $errMsg = $decoded['error']['message'] ?? $response;
            return ['success' => false, 'message' => 'Claude API error (' . $httpCode . '): ' . $errMsg];
        }

        $content = $decoded['content'][0]['text'] ?? '';
        // Strip markdown code fences if Claude wrapped the JSON
        $content = preg_replace('/^```(?:json)?\s*/m', '', trim($content));
        $content = preg_replace('/\s*```\s*$/m', '', $content);
        $content = trim($content);

        $aiData = json_decode($content, true);
        if (!is_array($aiData) || !isset($aiData['allocations'])) {
            return [
                'success' => false,
                'message' => 'AI returned an unexpected format. Please try again.',
                'raw'     => substr($content, 0, 500),
            ];
        }

        return ['success' => true, 'data' => $aiData, 'vendors' => $vendors];
    }

    // -----------------------------------------------------------------------
    // save()
    // Inserts or updates a budget proposal.
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function save(array $data, int $userId): array
    {
        $id                = (int)   ($data['id']                ?? 0);
        $title             = trim($data['title']                 ?? '');
        $eventDescription  = trim($data['event_description']     ?? '');
        $eventDemographics = trim($data['event_demographics']    ?? '');
        $budgetGood        = (float) ($data['budget_good']       ?? 0);
        $budgetBetter      = (float) ($data['budget_better']     ?? 0);
        $budgetBest        = (float) ($data['budget_best']       ?? 0);
        $allocationJson    = $data['allocation_json']            ?? '{}';
        $aiRationale       = trim($data['ai_rationale']          ?? '');
        $clientId          = (int)   ($data['client_id']         ?? 0);

        if ($title === '') {
            return ['success' => false, 'id' => 0, 'message' => 'Proposal title is required.'];
        }

        if ($id === 0) {
            $stmt = $this->db->prepare(
                'INSERT INTO budget_proposals
                     (title, event_description, event_demographics, budget_good, budget_better, budget_best,
                      allocation_json, ai_rationale, status, client_id, created_by, created_at, updated_at)
                 VALUES
                     (:title, :event_desc, :event_demo, :bg, :bb, :bbs,
                      :alloc, :rationale, "draft", :client_id, :created_by, NOW(), NOW())'
            );
            $stmt->execute([
                ':title'      => $title,
                ':event_desc' => $eventDescription,
                ':event_demo' => $eventDemographics,
                ':bg'         => $budgetGood,
                ':bb'         => $budgetBetter,
                ':bbs'        => $budgetBest,
                ':alloc'      => $allocationJson,
                ':rationale'  => $aiRationale,
                ':client_id'  => $clientId > 0 ? $clientId : null,
                ':created_by' => $userId,
            ]);

            $newId = $this->lastInsertId();
            $this->auditLog('create_budget_proposal', 'budget_proposal', $newId, "Created: {$title}");
            return ['success' => true, 'id' => $newId, 'message' => 'Proposal saved.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE budget_proposals
                SET title              = :title,
                    event_description  = :event_desc,
                    event_demographics = :event_demo,
                    budget_good        = :bg,
                    budget_better      = :bb,
                    budget_best        = :bbs,
                    allocation_json    = :alloc,
                    ai_rationale       = :rationale,
                    client_id          = :client_id,
                    updated_at         = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':title'      => $title,
            ':event_desc' => $eventDescription,
            ':event_demo' => $eventDemographics,
            ':bg'         => $budgetGood,
            ':bb'         => $budgetBetter,
            ':bbs'        => $budgetBest,
            ':alloc'      => $allocationJson,
            ':rationale'  => $aiRationale,
            ':client_id'  => $clientId > 0 ? $clientId : null,
            ':id'         => $id,
        ]);

        $this->auditLog('update_budget_proposal', 'budget_proposal', $id, "Updated: {$title}");
        return ['success' => true, 'id' => $id, 'message' => 'Proposal updated.'];
    }

    // -----------------------------------------------------------------------
    // getProposal()
    // Fetches a single budget proposal by ID.
    // -----------------------------------------------------------------------
    public function getProposal(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT bp.*, cl.company_name AS client_name, cl.email AS client_email
               FROM budget_proposals bp
          LEFT JOIN clients cl ON cl.id = bp.client_id
              WHERE bp.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : [];
    }

    // -----------------------------------------------------------------------
    // getProposals()
    // Returns paginated list of all budget proposals.
    // -----------------------------------------------------------------------
    public function getProposals(int $page = 1, int $pageSize = PAGE_SIZE): array
    {
        $sql = 'SELECT bp.*, cl.company_name AS client_name
                  FROM budget_proposals bp
             LEFT JOIN clients cl ON cl.id = bp.client_id
              ORDER BY bp.created_at DESC';
        return $this->paginate($sql, [], $page, $pageSize);
    }

    // -----------------------------------------------------------------------
    // markSent()
    // Updates proposal status to "sent".
    // -----------------------------------------------------------------------
    public function markSent(int $id): void
    {
        $this->db->prepare(
            'UPDATE budget_proposals
                SET status = "sent", sent_at = NOW(), updated_at = NOW()
              WHERE id = :id'
        )->execute([':id' => $id]);

        $this->auditLog('budget_proposal_sent', 'budget_proposal', $id, 'Marked as sent to client');
    }

    // -----------------------------------------------------------------------
    // markApproved()
    // Marks proposal as approved with the chosen budget tier.
    // -----------------------------------------------------------------------
    public function markApproved(int $id, string $tier): void
    {
        $tier = in_array($tier, ['good', 'better', 'best'], true) ? $tier : 'good';

        $this->db->prepare(
            'UPDATE budget_proposals
                SET status        = "approved",
                    approved_tier = :tier,
                    approved_at   = NOW(),
                    updated_at    = NOW()
              WHERE id = :id'
        )->execute([':tier' => $tier, ':id' => $id]);

        $this->auditLog('budget_proposal_approved', 'budget_proposal', $id, "Approved tier: {$tier}");
    }

    // -----------------------------------------------------------------------
    // convertToCampaign()
    // Creates a Campaign + campaign_channels from the approved tier allocation.
    //
    // Returns: ['success'=>bool, 'campaign_id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function convertToCampaign(int $proposalId, int $clientId, string $campaignTitle): array
    {
        $proposal = $this->getProposal($proposalId);
        if (empty($proposal)) {
            return ['success' => false, 'message' => 'Proposal not found.'];
        }
        if ($proposal['status'] !== 'approved') {
            return ['success' => false, 'message' => 'Proposal must be approved before converting to a campaign.'];
        }

        $tier      = $proposal['approved_tier'] ?? 'good';
        $alloc     = json_decode($proposal['allocation_json'] ?? '{}', true);
        $tierRows  = $alloc[$tier] ?? [];

        if (empty($tierRows)) {
            return ['success' => false, 'message' => "No allocations found for the '{$tier}' tier."];
        }

        $totalBudget = array_sum(array_column($tierRows, 'amount'));

        $campaignService = new CampaignService();

        $campResult = $campaignService->saveCampaign([
            'id'           => 0,
            'title'        => $campaignTitle,
            'client_id'    => $clientId,
            'status'       => 'approved',
            'total_budget' => $totalBudget,
            'notes'        => 'Converted from AI Budget Proposal #' . $proposalId . ' — ' . $proposal['title']
                            . ' (' . ucfirst($tier) . ' tier)',
        ]);

        if (!($campResult['success'] ?? false)) {
            return $campResult;
        }

        $campaignId = (int)$campResult['id'];

        foreach ($tierRows as $row) {
            $campaignService->saveChannel([
                'id'               => 0,
                'campaign_id'      => $campaignId,
                'vendor_id'        => (int)($row['vendor_id'] ?? 0),
                'media_category'   => $row['category'] ?? '',
                'budget_allocated' => (float)($row['amount'] ?? 0),
                'notes'            => $row['rationale'] ?? '',
            ]);
        }

        $this->db->prepare(
            'UPDATE budget_proposals
                SET status = "converted", campaign_id = :cid, updated_at = NOW()
              WHERE id = :id'
        )->execute([':cid' => $campaignId, ':id' => $proposalId]);

        $this->auditLog(
            'convert_to_campaign',
            'budget_proposal',
            $proposalId,
            "Converted to campaign #{$campaignId}"
        );

        return ['success' => true, 'campaign_id' => $campaignId, 'message' => 'Campaign created successfully.'];
    }

    // -----------------------------------------------------------------------
    // getActiveVendorsForAI()
    // Returns all active vendors with demographics and media_kit for the
    // AI prompt.
    // -----------------------------------------------------------------------
    public function getActiveVendorsForAI(): array
    {
        $stmt = $this->db->query(
            'SELECT id, company_name, media_category, demographics, media_kit
               FROM vendors
              WHERE is_active = 1
              ORDER BY media_category ASC, company_name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // buildUnifiedTable()
    // Merges Good/Better/Best allocations into a single vendor-keyed array
    // for display and editing.
    //
    // Returns: array of rows keyed by vendor_id:
    //   [vendor_id, vendor_name, category,
    //    good_amount, better_amount, best_amount,
    //    good_rationale, better_rationale, best_rationale]
    // -----------------------------------------------------------------------
    public static function buildUnifiedTable(array $allocations): array
    {
        $map = [];
        foreach (['good', 'better', 'best'] as $tier) {
            foreach ($allocations[$tier] ?? [] as $entry) {
                $vid = (int)($entry['vendor_id'] ?? 0);
                if ($vid === 0) {
                    continue;
                }
                if (!isset($map[$vid])) {
                    $map[$vid] = [
                        'vendor_id'        => $vid,
                        'vendor_name'      => $entry['vendor_name'] ?? '',
                        'category'         => $entry['category'] ?? '',
                        'good_amount'      => 0.0,
                        'better_amount'    => 0.0,
                        'best_amount'      => 0.0,
                        'good_rationale'   => '',
                        'better_rationale' => '',
                        'best_rationale'   => '',
                    ];
                }
                $map[$vid][$tier . '_amount']    = (float)($entry['amount']    ?? 0);
                $map[$vid][$tier . '_rationale'] = $entry['rationale'] ?? '';
            }
        }

        usort($map, fn($a, $b) =>
            strcmp($a['category'], $b['category']) ?: strcmp($a['vendor_name'], $b['vendor_name'])
        );

        return array_values($map);
    }

    // -----------------------------------------------------------------------
    // rebuildAllocationFromPost()
    // Converts the editable POST form data back into the allocations structure.
    //
    // Expects: $_POST['vendor_data'] array of rows
    // Returns: ['good'=>[], 'better'=>[], 'best'=>[]]
    // -----------------------------------------------------------------------
    public static function rebuildAllocationFromPost(array $vendorData): array
    {
        $allocations = ['good' => [], 'better' => [], 'best' => []];

        foreach ($vendorData as $row) {
            $vid  = (int)($row['vendor_id'] ?? 0);
            $name = trim($row['vendor_name'] ?? '');
            $cat  = trim($row['category']    ?? '');
            if ($vid === 0) {
                continue;
            }
            foreach (['good', 'better', 'best'] as $tier) {
                $amount = (float)($row[$tier . '_amount'] ?? 0);
                if ($amount > 0) {
                    $allocations[$tier][] = [
                        'vendor_id'   => $vid,
                        'vendor_name' => $name,
                        'category'    => $cat,
                        'amount'      => $amount,
                        'rationale'   => trim($row[$tier . '_rationale'] ?? ''),
                    ];
                }
            }
        }

        return $allocations;
    }
}
