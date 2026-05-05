<?php
/**
 * admin/vendors-export.php
 * Streams the full vendor list as a CSV download.
 */
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$crmService = new CRMService();
$vendors    = $crmService->getVendors();

$filename = 'vendors_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens it correctly
fwrite($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    'ID',
    'Company Name',
    'Contact Name',
    'Email',
    'Phone',
    'Billing Email',
    'Coverage Area',
    'Service Options',
    'Address',
    'Notes',
    'Demographics',
    'Media Kit',
    'Active',
    'Created',
]);

foreach ($vendors as $v) {
    // JSON arrays → readable comma-separated strings
    $coverageArea   = is_array($v['coverage_area'])   ? implode('; ', $v['coverage_area'])   : '';
    $serviceOptions = is_array($v['service_options'])  ? implode('; ', $v['service_options']) : '';

    fputcsv($out, [
        $v['id'],
        $v['company_name'],
        $v['contact_name']   ?? '',
        $v['email']          ?? '',
        $v['phone']          ?? '',
        $v['billing_email']  ?? '',
        $coverageArea,
        $serviceOptions,
        $v['address']        ?? '',
        $v['notes']          ?? '',
        $v['demographics']   ?? '',
        $v['media_kit']      ?? '',
        ($v['is_active'] ?? 1) ? 'Yes' : 'No',
        $v['created_at']     ?? '',
    ]);
}

fclose($out);
exit;
