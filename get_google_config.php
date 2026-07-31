<?php
// get_google_config.php — Lấy Google Client ID hiện tại
header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/google_config.json';

if (file_exists($configFile)) {
    $content = file_get_contents($configFile);
    $data = json_decode($content, true);
    echo json_encode([
        'success' => true,
        'client_id' => isset($data['client_id']) ? $data['client_id'] : ''
    ]);
} else {
    echo json_encode([
        'success' => true,
        'client_id' => ''
    ]);
}
