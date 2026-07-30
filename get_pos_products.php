<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/pos_products.json';
$products = [];
if (file_exists($file)) {
    $products = json_decode(file_get_contents($file), true) ?: [];
}

echo json_encode(['status' => 'ok', 'products' => $products], JSON_UNESCAPED_UNICODE);
