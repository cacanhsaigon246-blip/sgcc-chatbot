<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$qa = isset($input['qa']) ? $input['qa'] : null;
$pass = isset($input['pass']) ? $input['pass'] : '';

if ($pass !== 'sgcacanh2024') {
    echo json_encode(['status' => 'error', 'message' => 'Mật khẩu quản trị không đúng']);
    exit;
}

if (!is_array($qa)) {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

$file = __DIR__ . '/qa_pairs.json';
file_put_contents($file, json_encode($qa, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['status' => 'ok', 'qa' => $qa]);
