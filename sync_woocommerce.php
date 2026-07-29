<?php
/**
 * sync_woocommerce.php — Tự Động Đồng Bộ TOÀN BỘ 100% Bài Viết & Sản Phẩm Từ saigoncacanh.com
 * Sài Gòn Cá Cảnh Chatbot (Hỗ trợ Pagination Vòng Lặp)
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$all_products = [];
$page = 1;
$max_pages = 10; // Kéo tối đa 10 trang x 100 bài = 1000 bài viết/sản phẩm

do {
    $wp_url = "https://saigoncacanh.com/wp-json/wp/v2/posts?per_page=100&page={$page}";
    
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

    if ($http_code !== 200 || !$response) {
        break;
    }

    $posts = json_decode($response, true);
    if (!is_array($posts) || empty($posts)) {
        break;
    }

    foreach ($posts as $post) {
        $all_products[] = [
            'id'       => $post['id'] ?? 0,
            'name'     => html_entity_decode(strip_tags($post['title']['rendered'] ?? '')),
            'link'     => $post['link'] ?? '',
            'excerpt'  => html_entity_decode(strip_tags($post['excerpt']['rendered'] ?? ''))
        ];
    }

    $page++;
} while ($page <= $max_pages);

if (!empty($all_products)) {
    // Lưu vào file local json cache
    file_put_contents(__DIR__ . '/scraped_products.json', json_encode($all_products, JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'status' => 'success',
        'count'  => count($all_products),
        'data'   => $all_products
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Không thể kết nối API WordPress saigoncacanh.com'
    ], JSON_UNESCAPED_UNICODE);
}
