<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$res = [
    'status' => 'success',
    'members_count' => 0,
    'wp_posts_count' => 0,
    'wp_products_count' => 0,
    'pos_products_count' => 0,
    'has_key' => false
];

// 1. Đếm thành viên
$members_file = __DIR__ . '/members.json';
if (file_exists($members_file)) {
    $data = json_decode(file_get_contents($members_file), true);
    $res['members_count'] = is_array($data) ? count($data) : 0;
}

// 2. Đếm bài viết WordPress
$posts_file = __DIR__ . '/wordpress_posts.json';
if (file_exists($posts_file)) {
    $data = json_decode(file_get_contents($posts_file), true);
    $res['wp_posts_count'] = is_array($data) ? count($data) : 0;
}

// 3. Đếm sản phẩm WooCommerce
$woo_file = __DIR__ . '/woocommerce_products.json';
if (file_exists($woo_file)) {
    $data = json_decode(file_get_contents($woo_file), true);
    $res['wp_products_count'] = is_array($data) ? count($data) : 0;
}

// 4. Đếm sản phẩm POS
$pos_file = __DIR__ . '/pos_products.json';
if (file_exists($pos_file)) {
    $data = json_decode(file_get_contents($pos_file), true);
    $res['pos_products_count'] = is_array($data) ? count($data) : 0;
}

// 5. Kiểm tra API Key
$key_file = __DIR__ . '/gemini_key.txt';
if (file_exists($key_file) && trim(file_get_contents($key_file)) !== '') {
    $res['has_key'] = true;
}

echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
