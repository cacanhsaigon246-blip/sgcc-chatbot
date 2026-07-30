<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json; charset=utf-8');

$body = file_get_contents('php://input');
$data = json_decode($body, true);

$key = $data['key'] ?? '';
$pass = $data['pass'] ?? '';

// Kiểm tra pass giống như trong admin.html
if ($pass !== 'sgcacanh2024') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Sai mật khẩu Admin!']);
    exit();
}

if (!empty($key)) {
    file_put_contents(__DIR__ . '/gemini_key.txt', trim($key));
    echo json_encode(['status' => 'ok', 'message' => 'Lưu API Key thành công']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Thiếu API Key']);
}
