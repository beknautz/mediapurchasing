<?php
/**
 * src/CRMService.php
 * Media Buying Platform — client/vendor CRM and settings service
 */

class CRMService extends BaseService
{
    // =========================================================================
    // CLIENTS
    // =========================================================================

    // -----------------------------------------------------------------------
    // getClients()
    // Returns all active clients, optionally filtered by a search string that
    // matches against company name, contact name, or email.
    //
    // Returns: array of client rows
    // -----------------------------------------------------------------------
    public function getClients(string $search = ''): array
    {
        $sql    = 'SELECT id, company_name, contact_name,
                          email, phone, address, notes, is_active,
                          google_ads_customer_id, google_business_location,
                          created_at, updated_at
                     FROM clients';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE (company_name  LIKE :search
                          OR contact_name  LIKE :search
                          OR email         LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY company_name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // getClient()
    // Fetches a single client by ID including recent media buy history.
    //
    // Returns: ['client'=>row, 'recentBuys'=>rows] or [] if not found
    // -----------------------------------------------------------------------
    public function getClient(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM clients WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            return [];
        }

        $buyStmt = $this->db->prepare(
            'SELECT id, title, status, total_cost, agreed_cost, created_at
               FROM media_buys
              WHERE client_id = :id
              ORDER BY created_at DESC
              LIMIT 10'
        );
        $buyStmt->execute([':id' => $id]);
        $recentBuys = $buyStmt->fetchAll(PDO::FETCH_ASSOC);

        return ['client' => $client, 'recentBuys' => $recentBuys];
    }

    // -----------------------------------------------------------------------
    // saveClient()
    // Inserts a new client or updates an existing one.
    //
    // $data keys: id (0=insert), company_name, contact_name,
    //             email, phone, address, city, state, zip, country, is_active
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function saveClient(array $data): array
    {
        $id          = (int) ($data['id']           ?? 0);
        $companyName = trim($data['company_name']    ?? '');
        $contactName = trim($data['contact_name']    ?? '');
        $email       = trim(strtolower($data['email'] ?? ''));
        $phone       = trim($data['phone']           ?? '');
        $address     = trim($data['address']         ?? '');
        $notes       = trim($data['notes']            ?? '');
        $googleAdsId  = preg_replace('/\D/', '', $data['google_ads_customer_id'] ?? '') ?: null;
        $gbpLocation  = trim($data['google_business_location'] ?? '') ?: null;
        $isActive     = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if ($companyName === '') {
            return ['success' => false, 'id' => 0, 'message' => 'Company name is required.'];
        }

        if ($id === 0) {
            $stmt = $this->db->prepare(
                'INSERT INTO clients
                     (company_name, contact_name, email, phone,
                      address, notes, google_ads_customer_id, google_business_location, is_active, created_at, updated_at)
                 VALUES
                     (:company_name, :contact_name, :email, :phone,
                      :address, :notes, :google_ads_customer_id, :google_business_location, :is_active, NOW(), NOW())'
            );
            $stmt->execute([
                ':company_name'              => $companyName,
                ':contact_name'              => $contactName,
                ':email'                     => $email,
                ':phone'                     => $phone,
                ':address'                   => $address,
                ':notes'                     => $notes,
                ':google_ads_customer_id'    => $googleAdsId,
                ':google_business_location'  => $gbpLocation,
                ':is_active'                 => $isActive,
            ]);

            $newId = $this->lastInsertId();
            $this->auditLog('create_client', 'client', $newId, "Created: {$companyName}");

            return ['success' => true, 'id' => $newId, 'message' => 'Client created successfully.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE clients
                SET company_name              = :company_name,
                    contact_name              = :contact_name,
                    email                     = :email,
                    phone                     = :phone,
                    address                   = :address,
                    notes                     = :notes,
                    google_ads_customer_id    = :google_ads_customer_id,
                    google_business_location  = :google_business_location,
                    is_active                 = :is_active,
                    updated_at                = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':company_name'              => $companyName,
            ':contact_name'              => $contactName,
            ':email'                     => $email,
            ':phone'                     => $phone,
            ':address'                   => $address,
            ':notes'                     => $notes,
            ':google_ads_customer_id'    => $googleAdsId,
            ':google_business_location'  => $gbpLocation,
            ':is_active'                 => $isActive,
            ':id'                        => $id,
        ]);

        $this->auditLog('update_client', 'client', $id, "Updated: {$companyName}");

        return ['success' => true, 'id' => $id, 'message' => 'Client updated successfully.'];
    }

    public function deleteClient(int $id): void
    {
        $stmt = $this->db->prepare('SELECT company_name FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $name = $stmt->fetchColumn() ?: 'Unknown';

        $this->db->prepare('UPDATE clients SET is_active = 0, updated_at = NOW() WHERE id = :id')
            ->execute([':id' => $id]);

        $this->auditLog('delete_client', 'client', $id, "Deactivated: {$name}");
    }

    // =========================================================================
    // VENDORS
    // =========================================================================

    // -----------------------------------------------------------------------
    // getVendors()
    // Returns all vendors, optionally filtered by search string.
    //
    // Returns: array of vendor rows
    // -----------------------------------------------------------------------
    public function getVendors(string $search = ''): array
    {
        $sql    = 'SELECT id, company_name, contact_name,
                          email, phone, address, billing_email, media_category,
                          coverage_area, service_options,
                          demographics, media_kit,
                          is_active, created_at, updated_at
                     FROM vendors';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE (company_name LIKE :search
                          OR contact_name LIKE :search
                          OR email        LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY company_name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON columns so callers get arrays
        foreach ($rows as &$row) {
            $row['coverage_area']   = !empty($row['coverage_area'])
                ? json_decode($row['coverage_area'], true) : [];
            $row['service_options'] = !empty($row['service_options'])
                ? json_decode($row['service_options'], true) : [];
        }
        unset($row);

        return $rows;
    }

    // -----------------------------------------------------------------------
    // getVendor()
    // Fetches a single vendor by ID.
    //
    // Returns: ['vendor'=>row, 'recentBuys'=>rows] or [] if not found
    // -----------------------------------------------------------------------
    public function getVendor(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM vendors WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vendor) {
            return [];
        }

        $buyStmt = $this->db->prepare(
            'SELECT id, title, status, total_cost, agreed_cost, created_at
               FROM media_buys
              WHERE vendor_id = :id
              ORDER BY created_at DESC
              LIMIT 10'
        );
        $buyStmt->execute([':id' => $id]);
        $recentBuys = $buyStmt->fetchAll(PDO::FETCH_ASSOC);

        return ['vendor' => $vendor, 'recentBuys' => $recentBuys];
    }

    // -----------------------------------------------------------------------
    // saveVendor()
    // Inserts a new vendor or updates an existing one.
    //
    // $data keys: id (0=insert), company_name, contact_name,
    //             email, phone, address, city, state, zip, country, is_active
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function saveVendor(array $data): array
    {
        $id            = (int) ($data['id']            ?? 0);
        $companyName   = trim($data['company_name']    ?? '');
        $contactName   = trim($data['contact_name']    ?? '');
        $email         = trim(strtolower($data['email'] ?? ''));
        $phone         = trim($data['phone']            ?? '');
        $address       = trim($data['address']          ?? '');
        $billingEmail  = trim(strtolower($data['billing_email']  ?? ''));
        $mediaCategory = trim($data['media_category']   ?? '');
        $demographics  = trim($data['demographics']     ?? '');
        $mediaKit      = trim($data['media_kit']        ?? '');
        $isActive      = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        // JSON columns — accept array or already-encoded string
        $coverageArea   = $data['coverage_area']   ?? [];
        $serviceOptions = $data['service_options'] ?? [];
        if (is_string($coverageArea))   $coverageArea   = json_decode($coverageArea, true) ?? [];
        if (is_string($serviceOptions)) $serviceOptions = json_decode($serviceOptions, true) ?? [];
        $coverageAreaJson   = !empty($coverageArea)   ? json_encode(array_values($coverageArea))   : null;
        $serviceOptionsJson = !empty($serviceOptions) ? json_encode(array_values($serviceOptions)) : null;

        if ($companyName === '') {
            return ['success' => false, 'id' => 0, 'message' => 'Company name is required.'];
        }

        if ($id === 0) {
            $stmt = $this->db->prepare(
                'INSERT INTO vendors
                     (company_name, contact_name, email, phone,
                      address, billing_email, media_category,
                      coverage_area, service_options,
                      demographics, media_kit,
                      is_active, created_at, updated_at)
                 VALUES
                     (:company_name, :contact_name, :email, :phone,
                      :address, :billing_email, :media_category,
                      :coverage_area, :service_options,
                      :demographics, :media_kit,
                      :is_active, NOW(), NOW())'
            );
            $stmt->execute([
                ':company_name'    => $companyName,
                ':contact_name'    => $contactName,
                ':email'           => $email,
                ':phone'           => $phone,
                ':address'         => $address,
                ':billing_email'   => $billingEmail,
                ':media_category'  => $mediaCategory !== '' ? $mediaCategory : null,
                ':coverage_area'   => $coverageAreaJson,
                ':service_options' => $serviceOptionsJson,
                ':demographics'    => $demographics !== '' ? $demographics : null,
                ':media_kit'       => $mediaKit !== '' ? $mediaKit : null,
                ':is_active'       => $isActive,
            ]);

            $newId = $this->lastInsertId();
            $this->auditLog('create_vendor', 'vendor', $newId, "Created: {$companyName}");

            return ['success' => true, 'id' => $newId, 'message' => 'Vendor created successfully.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE vendors
                SET company_name    = :company_name,
                    contact_name    = :contact_name,
                    email           = :email,
                    phone           = :phone,
                    address         = :address,
                    billing_email   = :billing_email,
                    media_category  = :media_category,
                    coverage_area   = :coverage_area,
                    service_options = :service_options,
                    demographics    = :demographics,
                    media_kit       = :media_kit,
                    is_active       = :is_active,
                    updated_at      = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':company_name'    => $companyName,
            ':contact_name'    => $contactName,
            ':email'           => $email,
            ':phone'           => $phone,
            ':address'         => $address,
            ':billing_email'   => $billingEmail,
            ':media_category'  => $mediaCategory !== '' ? $mediaCategory : null,
            ':coverage_area'   => $coverageAreaJson,
            ':service_options' => $serviceOptionsJson,
            ':demographics'    => $demographics !== '' ? $demographics : null,
            ':media_kit'       => $mediaKit !== '' ? $mediaKit : null,
            ':is_active'       => $isActive,
            ':id'              => $id,
        ]);

        $this->auditLog('update_vendor', 'vendor', $id, "Updated: {$companyName}");

        return ['success' => true, 'id' => $id, 'message' => 'Vendor updated successfully.'];
    }

    // -----------------------------------------------------------------------
    // deleteVendor()
    // Permanently removes a vendor by ID.
    // -----------------------------------------------------------------------
    public function deleteVendor(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM vendors WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $this->auditLog('delete_vendor', 'vendor', $id, "Deleted vendor id={$id}");
    }

    // =========================================================================
    // EMAIL TEMPLATES
    // =========================================================================

    // -----------------------------------------------------------------------
    // getTemplates()
    // Returns all email templates, optionally filtered by category.
    //
    // Returns: array of template rows
    // -----------------------------------------------------------------------
    public function getTemplates(string $category = ''): array
    {
        $sql    = 'SELECT id, slug, name, category, subject, is_active, created_at, updated_at
                     FROM email_templates';
        $params = [];

        if ($category !== '') {
            $sql               .= ' WHERE category = :category';
            $params[':category'] = $category;
        }

        $sql .= ' ORDER BY category ASC, name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // getTemplate()
    // Fetches a single email template by ID.
    //
    // Returns: associative array of the row, or [] if not found
    // -----------------------------------------------------------------------
    public function getTemplate(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : [];
    }

    // -----------------------------------------------------------------------
    // saveTemplate()
    // Inserts or updates an email template.
    //
    // $data keys: id (0=insert), slug, name, category, subject, body_html,
    //             body_text, from_email, from_name, is_active
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function saveTemplate(array $data): array
    {
        $id        = (int) ($data['id']          ?? 0);
        $slug      = trim($data['slug']           ?? '');
        $name      = trim($data['name']           ?? '');
        $category  = trim($data['category']       ?? 'general');
        $subject   = trim($data['subject']        ?? '');
        $bodyHtml  = $data['body_html']           ?? '';
        $bodyText  = $data['body_text']           ?? '';
        $isActive  = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if ($slug === '' || $name === '' || $subject === '') {
            return ['success' => false, 'id' => 0, 'message' => 'Slug, name, and subject are required.'];
        }

        if ($id === 0) {
            $stmt = $this->db->prepare(
                'INSERT INTO email_templates
                     (slug, name, category, subject, body_html, body_text,
                      is_active, created_at, updated_at)
                 VALUES
                     (:slug, :name, :category, :subject, :body_html, :body_text,
                      :is_active, NOW(), NOW())'
            );
            $stmt->execute([
                ':slug'      => $slug,
                ':name'      => $name,
                ':category'  => $category,
                ':subject'   => $subject,
                ':body_html' => $bodyHtml,
                ':body_text' => $bodyText,
                ':is_active' => $isActive,
            ]);

            $newId = $this->lastInsertId();
            $this->auditLog('create_template', 'email_template', $newId, "Created: {$slug}");

            return ['success' => true, 'id' => $newId, 'message' => 'Template created successfully.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE email_templates
                SET slug      = :slug,
                    name      = :name,
                    category  = :category,
                    subject   = :subject,
                    body_html = :body_html,
                    body_text = :body_text,
                    is_active = :is_active,
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':slug'      => $slug,
            ':name'      => $name,
            ':category'  => $category,
            ':subject'   => $subject,
            ':body_html' => $bodyHtml,
            ':body_text' => $bodyText,
            ':is_active' => $isActive,
            ':id'        => $id,
        ]);

        $this->auditLog('update_template', 'email_template', $id, "Updated: {$slug}");

        return ['success' => true, 'id' => $id, 'message' => 'Template updated successfully.'];
    }

    // =========================================================================
    // WORKFLOW SETTINGS
    // =========================================================================

    // -----------------------------------------------------------------------
    // getWorkflowSettings()
    // Returns all workflow settings, optionally filtered by group.
    //
    // Returns: array of setting rows
    // -----------------------------------------------------------------------
    public function getWorkflowSettings(string $group = ''): array
    {
        $sql    = 'SELECT id, setting_key, setting_value, setting_group,
                          label, description, updated_at
                     FROM workflow_settings';
        $params = [];

        if ($group !== '') {
            $sql               .= ' WHERE setting_group = :group';
            $params[':group']   = $group;
        }

        $sql .= ' ORDER BY setting_group ASC, setting_key ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // saveSetting()
    // Upserts a single workflow setting by key.
    // -----------------------------------------------------------------------
    public function saveSetting(string $key, string $value): void
    {
        // MySQL INSERT … ON DUPLICATE KEY UPDATE
        $stmt = $this->db->prepare(
            'INSERT INTO workflow_settings (setting_key, setting_value, updated_at)
             VALUES (:key, :value, NOW())
             ON DUPLICATE KEY UPDATE setting_value = :value2, updated_at = NOW()'
        );
        $stmt->execute([':key' => $key, ':value' => $value, ':value2' => $value]);

        // Keep the in-memory cache in sync
        $GLOBALS['appSettings'][$key] = $value;
        $_ENV[$key]                   = $value;

        $this->auditLog('save_setting', 'workflow_settings', 0, "Key: {$key}");
    }
}
