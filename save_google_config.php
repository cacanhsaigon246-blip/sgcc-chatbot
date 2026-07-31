<?php
// save_google_config.php — Lưu Google Client ID an toàn vào server
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$client_id = isset($input['client_id']) ? trim($input['client_id']) : '';

$configFile = __DIR__ . '/google_config.json';

$data = [
    'client_id' => $client_id,
    'updated_at' => date('Y-m-d H:i:s')
];

if (file_put_contents($configFile, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'message' => 'Lưu Google Client ID thành công!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể ghi file cấu hình trên server.']);
}
