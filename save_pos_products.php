<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$pass = isset($input['pass']) ? $input['pass'] : '';
$products = isset($input['products']) ? $input['products'] : [];

if ($pass !== 'sgcacanh2024') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Mật khẩu quản trị không đúng']);
    exit;
}

$file = __DIR__ . '/pos_products.json';
file_put_contents($file, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['status' => 'ok', 'count' => count($products)]);
