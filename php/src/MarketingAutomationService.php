<?php
/**
 * src/MarketingAutomationService.php
 * Manages social posts, ad copy, campaigns, and ad schedules.
 */

class MarketingAutomationService extends BaseService
{
    // ── Dashboard ──────────────────────────────────────────────────────────

    public function getDashboardStats(): array
    {
        $row = $this->db->query(
            'SELECT
                (SELECT COUNT(*) FROM crm_social_posts    WHERE status = "draft"   AND deleted_at IS NULL) AS draft_posts,
                (SELECT COUNT(*) FROM crm_social_posts    WHERE status = "scheduled" AND deleted_at IS NULL) AS scheduled_posts,
                (SELECT COUNT(*) FROM crm_marketing_campaigns WHERE status = "active" AND deleted_at IS NULL) AS active_campaigns,
                (SELECT COUNT(*) FROM crm_ad_schedules    WHERE status IN("draft","ready") AND deleted_at IS NULL) AS pending_ads,
                (SELECT COUNT(*) FROM crm_social_posts    WHERE status = "failed"  AND deleted_at IS NULL)
                  + (SELECT COUNT(*) FROM crm_ad_schedules WHERE status = "failed" AND deleted_at IS NULL) AS failed_jobs'
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: ['draft_posts'=>0,'scheduled_posts'=>0,'active_campaigns'=>0,'pending_ads'=>0,'failed_jobs'=>0];
    }

    public function getUpcomingSchedule(int $days = 7): array
    {
        $stmt = $this->db->prepare(
            'SELECT "post" AS item_type, id, title AS name, platform, status, scheduled_at AS starts_at
               FROM crm_social_posts
              WHERE deleted_at IS NULL AND scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :d DAY)
             UNION ALL
             SELECT "ad" AS item_type, id, ad_name AS name, platform, status, start_datetime AS starts_at
               FROM crm_ad_schedules
              WHERE deleted_at IS NULL AND start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :d2 DAY)
             ORDER BY starts_at ASC LIMIT 20'
        );
        $stmt->execute([':d' => $days, ':d2' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Campaigns ──────────────────────────────────────────────────────────

    public function getCampaigns(string $status = ''): array
    {
        $where  = 'c.deleted_at IS NULL';
        $params = [];
        if ($status) { $where .= ' AND c.status = :status'; $params[':status'] = $status; }

        $stmt = $this->db->prepare(
            "SELECT c.*,
                    COUNT(DISTINCT sp.id)  AS post_count,
                    COUNT(DISTINCT ads.id) AS schedule_count
               FROM crm_marketing_campaigns c
          LEFT JOIN crm_social_posts    sp  ON sp.campaign_id  = c.id AND sp.deleted_at  IS NULL
          LEFT JOIN crm_ad_schedules    ads ON ads.campaign_id = c.id AND ads.deleted_at IS NULL
              WHERE {$where}
           GROUP BY c.id
           ORDER BY c.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCampaign(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_marketing_campaigns WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveCampaign(array $d): int
    {
        $id = (int)($d['id'] ?? 0);
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $fields = [
            ':campaign_name' => trim($d['campaign_name'] ?? ''),
            ':campaign_type' => $d['campaign_type'] ?? 'awareness',
            ':platform'      => $d['platform']      ?? 'meta',
            ':objective'     => trim($d['objective'] ?? '') ?: null,
            ':budget_daily'  => $d['budget_daily']  !== '' ? round((float)$d['budget_daily'],  2) : null,
            ':budget_total'  => $d['budget_total']  !== '' ? round((float)$d['budget_total'],  2) : null,
            ':start_date'    => $d['start_date']    ?: null,
            ':end_date'      => $d['end_date']      ?: null,
            ':status'        => $d['status']        ?? 'draft',
            ':notes'         => trim($d['notes']    ?? '') ?: null,
        ];
        if ($id > 0) {
            $fields[':id'] = $id;
            $this->db->prepare(
                'UPDATE crm_marketing_campaigns SET campaign_name=:campaign_name, campaign_type=:campaign_type,
                  platform=:platform, objective=:objective, budget_daily=:budget_daily, budget_total=:budget_total,
                  start_date=:start_date, end_date=:end_date, status=:status, notes=:notes, updated_at=NOW()
                  WHERE id=:id'
            )->execute($fields);
            $this->logActivity('campaign', $id, 'updated');
            return $id;
        }
        $fields[':created_by'] = $userId ?: null;
        $this->db->prepare(
            'INSERT INTO crm_marketing_campaigns
                (campaign_name,campaign_type,platform,objective,budget_daily,budget_total,start_date,end_date,status,notes,created_by)
             VALUES
                (:campaign_name,:campaign_type,:platform,:objective,:budget_daily,:budget_total,:start_date,:end_date,:status,:notes,:created_by)'
        )->execute($fields);
        $newId = $this->lastInsertId();
        $this->logActivity('campaign', $newId, 'created');
        return $newId;
    }

    public function deleteCampaign(int $id): void
    {
        $this->db->prepare('UPDATE crm_marketing_campaigns SET deleted_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
        $this->logActivity('campaign', $id, 'deleted');
    }

    // ── Social Posts ───────────────────────────────────────────────────────

    public function getSocialPosts(array $filters = []): array
    {
        $where  = 'p.deleted_at IS NULL';
        $params = [];
        if (!empty($filters['status']))      { $where .= ' AND p.status = :status';           $params[':status']      = $filters['status']; }
        if (!empty($filters['platform']))    { $where .= ' AND p.platform = :platform';        $params[':platform']    = $filters['platform']; }
        if (!empty($filters['campaign_id'])) { $where .= ' AND p.campaign_id = :campaign_id'; $params[':campaign_id'] = (int)$filters['campaign_id']; }

        $stmt = $this->db->prepare(
            "SELECT p.*, c.campaign_name
               FROM crm_social_posts p
          LEFT JOIN crm_marketing_campaigns c ON c.id = p.campaign_id
              WHERE {$where}
           ORDER BY p.scheduled_at IS NULL ASC, p.scheduled_at ASC, p.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSocialPost(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_social_posts WHERE id=:id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveSocialPost(array $d): int
    {
        $id     = (int)($d['id'] ?? 0);
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $fields = [
            ':campaign_id'  => $d['campaign_id'] ? (int)$d['campaign_id'] : null,
            ':platform'     => $d['platform']    ?? 'facebook',
            ':title'        => trim($d['title']  ?? ''),
            ':caption'      => trim($d['caption'] ?? '') ?: null,
            ':image_url'    => trim($d['image_url']  ?? '') ?: null,
            ':video_url'    => trim($d['video_url']  ?? '') ?: null,
            ':status'       => $d['status']      ?? 'draft',
            ':scheduled_at' => $d['scheduled_at'] ?: null,
            ':notes'        => trim($d['notes']   ?? '') ?: null,
        ];
        if ($id > 0) {
            $fields[':id'] = $id;
            $this->db->prepare(
                'UPDATE crm_social_posts SET campaign_id=:campaign_id, platform=:platform, title=:title,
                  caption=:caption, image_url=:image_url, video_url=:video_url, status=:status,
                  scheduled_at=:scheduled_at, notes=:notes, updated_at=NOW() WHERE id=:id'
            )->execute($fields);
            $this->logActivity('social_post', $id, 'updated');
            return $id;
        }
        $fields[':created_by'] = $userId ?: null;
        $this->db->prepare(
            'INSERT INTO crm_social_posts
                (campaign_id,platform,title,caption,image_url,video_url,status,scheduled_at,notes,created_by)
             VALUES
                (:campaign_id,:platform,:title,:caption,:image_url,:video_url,:status,:scheduled_at,:notes,:created_by)'
        )->execute($fields);
        $newId = $this->lastInsertId();
        $this->logActivity('social_post', $newId, 'created');
        return $newId;
    }

    public function deleteSocialPost(int $id): void
    {
        $this->db->prepare('UPDATE crm_social_posts SET deleted_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
        $this->logActivity('social_post', $id, 'deleted');
    }

    public function duplicateSocialPost(int $id): int
    {
        $post = $this->getSocialPost($id);
        if (!$post) throw new Exception('Post not found.');
        $post['id']     = 0;
        $post['title']  = 'Copy of ' . $post['title'];
        $post['status'] = 'draft';
        $post['scheduled_at'] = null;
        return $this->saveSocialPost($post);
    }

    // ── Ad Copy ────────────────────────────────────────────────────────────

    public function getAdCopy(array $filters = []): array
    {
        $where  = '1=1';
        $params = [];
        if (!empty($filters['status']))      { $where .= ' AND status = :status';           $params[':status']      = $filters['status']; }
        if (!empty($filters['platform']))    { $where .= ' AND platform = :platform';        $params[':platform']    = $filters['platform']; }
        if (!empty($filters['campaign_id'])) { $where .= ' AND campaign_id = :campaign_id'; $params[':campaign_id'] = (int)$filters['campaign_id']; }

        $stmt = $this->db->prepare(
            "SELECT ac.*, c.campaign_name
               FROM crm_ad_copy ac
          LEFT JOIN crm_marketing_campaigns c ON c.id = ac.campaign_id
              WHERE {$where}
           ORDER BY ac.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdCopyById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_ad_copy WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Placeholder AI generation — replace body with OpenAI/Claude API call later.
     * Returns a structured array of generated copy.
     */
    public function generateAdCopy(array $inputs): array
    {
        $biz      = $inputs['business_service'] ?? 'your business';
        $offer    = $inputs['offer']            ?? 'special offer';
        $audience = $inputs['target_audience']  ?? 'your audience';
        $location = $inputs['location']         ?? 'your area';
        $tone     = $inputs['tone']             ?? 'professional';
        $platform = $inputs['platform']         ?? 'meta';

        // ── TODO: Replace this block with API call ─────────────────────────
        // Meta:   POST https://api.openai.com/v1/chat/completions
        // Claude: POST https://api.anthropic.com/v1/messages
        // Pass $inputs as the prompt context. Parse JSON response into the
        // structure returned below.
        // ──────────────────────────────────────────────────────────────────

        if ($platform === 'meta') {
            return [
                'platform'         => 'meta',
                'headline'         => ucfirst($tone) . ': ' . $biz . ' — ' . $offer,
                'primary_text'     => "Looking for {$offer}? {$biz} is here to help {$audience} in {$location}. Don't miss out — act now!",
                'description'      => "Serving {$audience} in {$location}. Trusted. Proven. Ready for you.",
                'call_to_action'   => 'Learn More',
                'suggested_audience' => $audience . ', located in ' . $location,
            ];
        }

        return [
            'platform'            => 'google',
            'google_headlines'    => [
                $biz . ' | ' . $offer,
                'Serving ' . $location . ' — Call Today',
                'Trusted by ' . $audience,
            ],
            'google_descriptions' => [
                "Get {$offer} from {$biz}. Serving {$audience} in {$location}. Contact us today.",
                "Reliable, professional service for {$audience}. Call now or visit our site.",
            ],
            'suggested_keywords'  => [
                $biz, $offer, $location, $audience, $biz . ' ' . $location,
            ],
            'suggested_audience'  => $audience . ' near ' . $location,
        ];
    }

    public function saveAdCopy(array $d): int
    {
        $id     = (int)($d['id'] ?? 0);
        $userId = (int)($_SESSION['user']['id'] ?? 0);

        $googleHeadlines    = !empty($d['google_headlines'])    ? json_encode($d['google_headlines'])    : null;
        $googleDescriptions = !empty($d['google_descriptions']) ? json_encode($d['google_descriptions']) : null;
        $suggestedKeywords  = !empty($d['suggested_keywords'])  ? json_encode($d['suggested_keywords'])  : null;

        $fields = [
            ':campaign_id'      => $d['campaign_id'] ? (int)$d['campaign_id'] : null,
            ':platform'         => $d['platform']         ?? 'meta',
            ':business_service' => trim($d['business_service'] ?? '') ?: null,
            ':offer'            => trim($d['offer']       ?? '') ?: null,
            ':target_audience'  => trim($d['target_audience'] ?? '') ?: null,
            ':location'         => trim($d['location']    ?? '') ?: null,
            ':tone'             => $d['tone']             ?: null,
            ':objective'        => $d['objective']        ?: null,
            ':headline'         => trim($d['headline']    ?? '') ?: null,
            ':primary_text'     => trim($d['primary_text'] ?? '') ?: null,
            ':description'      => trim($d['description'] ?? '') ?: null,
            ':call_to_action'   => trim($d['call_to_action'] ?? '') ?: null,
            ':google_headlines'    => $googleHeadlines,
            ':google_descriptions' => $googleDescriptions,
            ':suggested_keywords'  => $suggestedKeywords,
            ':suggested_audience'  => trim($d['suggested_audience'] ?? '') ?: null,
            ':status'           => $d['status'] ?? 'draft',
            ':ai_generated'     => (int)($d['ai_generated'] ?? 1),
        ];

        if ($id > 0) {
            $fields[':id'] = $id;
            $this->db->prepare(
                'UPDATE crm_ad_copy SET campaign_id=:campaign_id, platform=:platform,
                  business_service=:business_service, offer=:offer, target_audience=:target_audience,
                  location=:location, tone=:tone, objective=:objective, headline=:headline,
                  primary_text=:primary_text, description=:description, call_to_action=:call_to_action,
                  google_headlines=:google_headlines, google_descriptions=:google_descriptions,
                  suggested_keywords=:suggested_keywords, suggested_audience=:suggested_audience,
                  status=:status, ai_generated=:ai_generated, updated_at=NOW() WHERE id=:id'
            )->execute($fields);
            $this->logActivity('ad_copy', $id, 'updated');
            return $id;
        }
        $fields[':created_by'] = $userId ?: null;
        $this->db->prepare(
            'INSERT INTO crm_ad_copy
                (campaign_id,platform,business_service,offer,target_audience,location,tone,objective,
                 headline,primary_text,description,call_to_action,google_headlines,google_descriptions,
                 suggested_keywords,suggested_audience,status,ai_generated,created_by)
             VALUES
                (:campaign_id,:platform,:business_service,:offer,:target_audience,:location,:tone,:objective,
                 :headline,:primary_text,:description,:call_to_action,:google_headlines,:google_descriptions,
                 :suggested_keywords,:suggested_audience,:status,:ai_generated,:created_by)'
        )->execute($fields);
        $newId = $this->lastInsertId();
        $this->logActivity('ad_copy', $newId, 'created');
        return $newId;
    }

    public function approveAdCopy(int $id, string $status): void
    {
        $allowed = ['approved','rejected','draft'];
        if (!in_array($status, $allowed, true)) throw new Exception('Invalid status.');
        $this->db->prepare('UPDATE crm_ad_copy SET status=:s, updated_at=NOW() WHERE id=:id')
            ->execute([':s'=>$status,':id'=>$id]);
        $this->logActivity('ad_copy', $id, $status);
    }

    // ── Ad Schedules ───────────────────────────────────────────────────────

    public function getAdSchedules(array $filters = []): array
    {
        $where  = 's.deleted_at IS NULL';
        $params = [];
        if (!empty($filters['status']))      { $where .= ' AND s.status = :status';           $params[':status']      = $filters['status']; }
        if (!empty($filters['platform']))    { $where .= ' AND s.platform = :platform';        $params[':platform']    = $filters['platform']; }
        if (!empty($filters['campaign_id'])) { $where .= ' AND s.campaign_id = :campaign_id'; $params[':campaign_id'] = (int)$filters['campaign_id']; }

        $stmt = $this->db->prepare(
            "SELECT s.*, c.campaign_name, ac.headline
               FROM crm_ad_schedules s
          LEFT JOIN crm_marketing_campaigns c  ON c.id  = s.campaign_id
          LEFT JOIN crm_ad_copy             ac ON ac.id = s.ad_copy_id
              WHERE {$where}
           ORDER BY s.start_datetime IS NULL ASC, s.start_datetime ASC, s.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdSchedule(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_ad_schedules WHERE id=:id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveAdSchedule(array $d): int
    {
        $id     = (int)($d['id'] ?? 0);
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $fields = [
            ':campaign_id'     => $d['campaign_id']  ? (int)$d['campaign_id']  : null,
            ':ad_copy_id'      => $d['ad_copy_id']   ? (int)$d['ad_copy_id']   : null,
            ':platform'        => $d['platform']     ?? 'meta',
            ':ad_name'         => trim($d['ad_name'] ?? ''),
            ':start_datetime'  => $d['start_datetime'] ?: null,
            ':end_datetime'    => $d['end_datetime']   ?: null,
            ':daily_budget'    => $d['daily_budget']  !== '' ? round((float)$d['daily_budget'], 2) : null,
            ':target_location' => trim($d['target_location'] ?? '') ?: null,
            ':target_audience' => trim($d['target_audience'] ?? '') ?: null,
            ':status'          => $d['status']  ?? 'draft',
            ':notes'           => trim($d['notes'] ?? '') ?: null,
        ];
        if ($id > 0) {
            $fields[':id'] = $id;
            $this->db->prepare(
                'UPDATE crm_ad_schedules SET campaign_id=:campaign_id, ad_copy_id=:ad_copy_id,
                  platform=:platform, ad_name=:ad_name, start_datetime=:start_datetime,
                  end_datetime=:end_datetime, daily_budget=:daily_budget, target_location=:target_location,
                  target_audience=:target_audience, status=:status, notes=:notes, updated_at=NOW() WHERE id=:id'
            )->execute($fields);
            $this->logActivity('ad_schedule', $id, 'updated');
            return $id;
        }
        $fields[':created_by'] = $userId ?: null;
        $this->db->prepare(
            'INSERT INTO crm_ad_schedules
                (campaign_id,ad_copy_id,platform,ad_name,start_datetime,end_datetime,daily_budget,
                 target_location,target_audience,status,notes,created_by)
             VALUES
                (:campaign_id,:ad_copy_id,:platform,:ad_name,:start_datetime,:end_datetime,:daily_budget,
                 :target_location,:target_audience,:status,:notes,:created_by)'
        )->execute($fields);
        $newId = $this->lastInsertId();
        $this->logActivity('ad_schedule', $newId, 'created');
        return $newId;
    }

    public function updateScheduleStatus(int $id, string $status): void
    {
        $allowed = ['draft','ready','scheduled','active','paused','completed','failed'];
        if (!in_array($status, $allowed, true)) throw new Exception('Invalid status.');
        $this->db->prepare('UPDATE crm_ad_schedules SET status=:s, updated_at=NOW() WHERE id=:id')
            ->execute([':s'=>$status,':id'=>$id]);
        $this->logActivity('ad_schedule', $id, 'status_changed', $status);
    }

    public function deleteAdSchedule(int $id): void
    {
        $this->db->prepare('UPDATE crm_ad_schedules SET deleted_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
        $this->logActivity('ad_schedule', $id, 'deleted');
    }

    // ── Activity Log ───────────────────────────────────────────────────────

    public function logActivity(string $entityType, int $entityId, string $action, string $detail = ''): void
    {
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        try {
            $this->db->prepare(
                'INSERT INTO crm_marketing_activity_log (entity_type,entity_id,action,detail,created_by)
                 VALUES (:et,:eid,:action,:detail,:uid)'
            )->execute([
                ':et'     => $entityType,
                ':eid'    => $entityId,
                ':action' => $action,
                ':detail' => $detail ?: null,
                ':uid'    => $userId ?: null,
            ]);
        } catch (Exception $e) {
            // Non-fatal
        }
    }

    // ── API Placeholders ───────────────────────────────────────────────────

    /**
     * Placeholder — connect Meta Marketing API here.
     * @see https://developers.facebook.com/docs/marketing-apis
     */
    public function publishToMeta(int $scheduleId): array
    {
        // TODO: Implement Meta Marketing API call
        // 1. Load schedule + ad copy by $scheduleId
        // 2. POST to https://graph.facebook.com/v18.0/act_{AD_ACCOUNT_ID}/ads
        // 3. Update schedule status to 'active' on success
        throw new Exception('Meta API not yet connected. Configure META_ACCESS_TOKEN and META_AD_ACCOUNT_ID first.');
    }

    /**
     * Placeholder — connect Google Ads API here.
     * @see https://developers.google.com/google-ads/api/docs/start
     */
    public function publishToGoogle(int $scheduleId): array
    {
        // TODO: Implement Google Ads API call
        // 1. Load schedule + ad copy by $scheduleId
        // 2. Use Google Ads PHP client library
        // 3. Update schedule status to 'active' on success
        throw new Exception('Google Ads API not yet connected. Configure GOOGLE_ADS_DEVELOPER_TOKEN first.');
    }

    /**
     * Placeholder — validate a Meta ad before publishing.
     */
    public function validateMetaAd(array $adData): array
    {
        // TODO: Validate headline length <= 40 chars, primary text <= 125 chars, etc.
        return ['valid' => true, 'warnings' => []];
    }

    /**
     * Placeholder — validate a Google ad before publishing.
     */
    public function validateGoogleAd(array $adData): array
    {
        // TODO: Validate headline <= 30 chars each, descriptions <= 90 chars each
        return ['valid' => true, 'warnings' => []];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getCampaignOptions(): array
    {
        return $this->db->query(
            'SELECT id, campaign_name FROM crm_marketing_campaigns WHERE deleted_at IS NULL ORDER BY campaign_name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getApprovedAdCopyOptions(string $platform = ''): array
    {
        $where = "status = 'approved'";
        if ($platform) $where .= " AND platform = " . $this->db->quote($platform);
        return $this->db->query(
            "SELECT id, headline, platform FROM crm_ad_copy WHERE {$where} ORDER BY created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
