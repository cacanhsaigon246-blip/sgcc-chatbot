<?php
/**
 * sync_woocommerce.php — Tự Động Đồng Bộ Sản Phẩm Từ WooCommerce / WordPress
 * Sài Gòn Cá Cảnh Chatbot
 *
 * Endpoint này cào/lấy danh sách sản phẩm từ saigoncacanh.com về dạng JSON
 * để Chatbot tự động cập nhật sản phẩm & bài viết mới nhất.
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$wp_url = 'https://saigoncacanh.com/wp-json/wp/v2/posts?per_page=50';

$ch = curl_init($wp_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SGCC-Chatbot/1.0'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && $response) {
    $posts = json_decode($response, true);
    $products = [];
    if (is_array($posts)) {
        foreach ($posts as $post) {
            $products[] = [
                'id'       => $post['id'] ?? 0,
                'name'     => html_entity_decode(strip_tags($post['title']['rendered'] ?? '')),
                'link'     => $post['link'] ?? '',
                'excerpt'  => html_entity_decode(strip_tags($post['excerpt']['rendered'] ?? ''))
            ];
        }
    }
    echo json_encode([
        'status' => 'success',
        'count'  => count($products),
        'data'   => $products
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Không thể lấy dữ liệu sản phẩm từ saigoncacanh.com'
    ], JSON_UNESCAPED_UNICODE);
}
