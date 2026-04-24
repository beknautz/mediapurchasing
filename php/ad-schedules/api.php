<?php
/**
 * AJAX endpoint for ad schedule inline checkbox toggles.
 * Expects POST: action, id, field
 * Returns JSON: { success, new_value }
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id']    ?? 0);
$field  = $_POST['field'] ?? '';

$svc = new AdScheduleService();

if ($action === 'toggle' && $id > 0 && $field !== '') {
    try {
        $newVal = $svc->toggleField($id, $field);
        echo json_encode(['success' => true, 'new_value' => (int)$newVal]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid request']);
