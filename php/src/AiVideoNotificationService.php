<?php
/**
 * src/AiVideoNotificationService.php
 * AI Video Studio — email notification service.
 */

class AiVideoNotificationService extends BaseService
{
    private SmtpMailer $mailer;

    public function __construct()
    {
        parent::__construct();
        $this->mailer = new SmtpMailer();
    }

    // -----------------------------------------------------------------------
    // notifyAdminVideoReady()
    // Emails the admin when a video job has been approved.
    // -----------------------------------------------------------------------
    public function notifyAdminVideoReady(int $campaignId, int $jobId): bool
    {
        $campaign = $this->getCampaign($campaignId);
        $job      = $this->getJob($jobId);
        if (!$campaign || !$job) {
            return false;
        }

        $adminEmail = ADMIN_NOTIFICATION_EMAIL;
        if (!$adminEmail) {
            error_log('[AiVideoNotificationService] ADMIN_NOTIFICATION_EMAIL not configured.');
            return false;
        }

        $subject = 'Video Approved: ' . $campaign['campaign_name'];
        $html    = $this->renderAdminReadyEmail($campaign, $job);

        $sent = $this->mailer->send($adminEmail, 'MediaBuy Admin', $subject, $html);

        $this->logNotification(
            $campaignId, $jobId, (int)($campaign['client_id'] ?? 0),
            'admin_video_approved', $adminEmail, $subject, strip_tags($html), $sent
        );

        return $sent;
    }

    // -----------------------------------------------------------------------
    // notifyClientReviewReady()
    // Emails the client with a secure portal link to review the video.
    // -----------------------------------------------------------------------
    public function notifyClientReviewReady(int $campaignId, int $jobId): bool
    {
        $campaign = $this->getCampaign($campaignId);
        $job      = $this->getJob($jobId);
        if (!$campaign) {
            return false;
        }

        $clientEmail = $this->getClientEmail((int)($campaign['client_id'] ?? 0));
        $clientName  = $this->getClientName((int)($campaign['client_id'] ?? 0));

        if (!$clientEmail) {
            error_log('[AiVideoNotificationService] No client email found for campaign ' . $campaignId);
            return false;
        }

        $token      = $campaign['review_token'] ?? '';
        $reviewUrl  = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/portal/video-review.php?token=' . urlencode($token);

        $subject = 'Your Video is Ready for Review — ' . $campaign['campaign_name'];
        $html    = $this->renderClientReviewEmail($campaign, $job, $clientName, $reviewUrl);

        $sent = $this->mailer->send($clientEmail, $clientName ?: 'Valued Client', $subject, $html);

        $this->logNotification(
            $campaignId, $jobId, (int)($campaign['client_id'] ?? 0),
            'client_review_ready', $clientEmail, $subject, strip_tags($html), $sent
        );

        return $sent;
    }

    // -----------------------------------------------------------------------
    // notifyRevisionRequested()
    // Emails the internal team when a client requests a revision.
    // -----------------------------------------------------------------------
    public function notifyRevisionRequested(int $campaignId, int $reviewId): bool
    {
        $campaign = $this->getCampaign($campaignId);
        if (!$campaign) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_reviews WHERE id = :id');
        $stmt->execute([':id' => $reviewId]);
        $review = $stmt->fetch();

        $adminEmail = ADMIN_NOTIFICATION_EMAIL;
        if (!$adminEmail) {
            return false;
        }

        $subject = 'Revision Requested: ' . $campaign['campaign_name'];
        $html    = $this->renderRevisionEmail($campaign, $review ?? []);

        $sent = $this->mailer->send($adminEmail, 'MediaBuy Team', $subject, $html);

        $this->logNotification(
            $campaignId, (int)($review['job_id'] ?? 0), (int)($campaign['client_id'] ?? 0),
            'revision_requested', $adminEmail, $subject, strip_tags($html), $sent
        );

        return $sent;
    }

    // -----------------------------------------------------------------------
    // PRIVATE helpers
    // -----------------------------------------------------------------------
    private function getCampaign(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function getJob(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function getClientEmail(int $clientId): string
    {
        if (!$clientId) return '';
        try {
            $stmt = $this->db->prepare(
                'SELECT email FROM clients WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $clientId]);
            return (string)($stmt->fetchColumn() ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function getClientName(int $clientId): string
    {
        if (!$clientId) return '';
        try {
            $stmt = $this->db->prepare(
                'SELECT COALESCE(contact_name, company_name, name, "") FROM clients WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $clientId]);
            return (string)($stmt->fetchColumn() ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function logNotification(
        int    $campaignId,
        int    $jobId,
        int    $clientId,
        string $type,
        string $email,
        string $subject,
        string $message,
        bool   $sent
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO ai_video_notifications
                (campaign_id, job_id, client_id, notification_type, recipient_email,
                 subject, message, sent_status, sent_at, created_at)
             VALUES
                (:campaign_id, :job_id, :client_id, :type, :email,
                 :subject, :message, :status, :sent_at, NOW())'
        );
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':job_id'      => $jobId ?: null,
            ':client_id'   => $clientId,
            ':type'        => $type,
            ':email'       => $email,
            ':subject'     => $subject,
            ':message'     => substr($message, 0, 5000),
            ':status'      => $sent ? 'sent' : 'failed',
            ':sent_at'     => $sent ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private function renderAdminReadyEmail(array $campaign, array $job): string
    {
        $name   = htmlspecialchars($campaign['campaign_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars($campaign['status'] ?? '', ENT_QUOTES, 'UTF-8');
        $jobId  = (int)($job['id'] ?? 0);
        $url    = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/admin/ai-video/view-campaign.php?id=' . (int)$campaign['id'];

        return <<<HTML
<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
  <h2 style="color:#198754;">&#10003; Video Approved</h2>
  <p>The following video campaign has been <strong>approved</strong> by the client:</p>
  <table style="width:100%;border-collapse:collapse;">
    <tr><td style="padding:6px;color:#666;">Campaign:</td><td style="padding:6px;"><strong>{$name}</strong></td></tr>
    <tr><td style="padding:6px;color:#666;">Job ID:</td><td style="padding:6px;">{$jobId}</td></tr>
    <tr><td style="padding:6px;color:#666;">Status:</td><td style="padding:6px;">{$status}</td></tr>
  </table>
  <p style="margin-top:20px;">
    <a href="{$url}" style="background:#0d6efd;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;">View Campaign</a>
  </p>
</div>
HTML;
    }

    private function renderClientReviewEmail(array $campaign, ?array $job, string $clientName, string $reviewUrl): string
    {
        $name    = htmlspecialchars($campaign['campaign_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $client  = htmlspecialchars($clientName ?: 'there', ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($reviewUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
  <h2 style="color:#0d6efd;">Your Video is Ready!</h2>
  <p>Hi {$client},</p>
  <p>Your video for <strong>{$name}</strong> is ready for your review. Please click the button below to watch the video and share your feedback.</p>
  <p style="margin:24px 0;text-align:center;">
    <a href="{$safeUrl}" style="background:#0d6efd;color:#fff;padding:14px 28px;border-radius:6px;text-decoration:none;font-size:16px;font-weight:bold;">Review Your Video</a>
  </p>
  <p style="color:#666;font-size:13px;">If the button doesn't work, copy and paste this link into your browser:<br>{$safeUrl}</p>
  <p style="color:#666;font-size:13px;">This secure link is unique to your campaign. Please do not share it.</p>
  <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
  <p style="color:#999;font-size:12px;">MediaBuy Platform &mdash; AI Video Studio</p>
</div>
HTML;
    }

    private function renderRevisionEmail(array $campaign, array $review): string
    {
        $name  = htmlspecialchars($campaign['campaign_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $notes = htmlspecialchars($review['revision_notes'] ?? 'No notes provided.', ENT_QUOTES, 'UTF-8');
        $url   = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/admin/ai-video/view-campaign.php?id=' . (int)$campaign['id'];

        return <<<HTML
<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">
  <h2 style="color:#dc3545;">Revision Requested</h2>
  <p>The client has requested revisions for <strong>{$name}</strong>.</p>
  <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:12px;margin:16px 0;">
    <strong>Revision Notes:</strong><br>{$notes}
  </div>
  <p>
    <a href="{$url}" style="background:#dc3545;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;">View Campaign &amp; Revise</a>
  </p>
</div>
HTML;
    }
}
