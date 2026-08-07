<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/interested_products.json';
$products = [];

if (file_exists($file)) {
    $products = json_decode(file_get_contents($file), true) ?: [];
}

// Sắp xếp theo số lượng tìm kiếm giảm dần
usort($products, function($a, $b) {
    return ($b['count'] ?? 0) - ($a['count'] ?? 0);
});

echo json_encode([
    'status' => 'ok',
    'products' => $products
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
