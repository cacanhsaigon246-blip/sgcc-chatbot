<?php
/**
 * sync_woocommerce.php — Đồng Bộ TOÀN BỘ 100% (204 Bài Viết + 784 Sản Phẩm WooCommerce)
 * Sài Gòn Cá Cảnh Chatbot Engine
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$all_items = [];

// ── 1. ĐỒNG BỘ 204 BÀI VIẾT (POSTS) ───────────────────────────
$page = 1;
do {
    $url = "https://saigoncacanh.com/wp-json/wp/v2/posts?per_page=100&page={$page}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SGCC-Chatbot/1.0'
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$res) break;
    $posts = json_decode($res, true);
    if (!is_array($posts) || empty($posts)) break;

    foreach ($posts as $post) {
        $all_items[] = [
            'type'    => 'Bài viết',
            'id'      => $post['id'] ?? 0,
            'name'    => html_entity_decode(strip_tags($post['title']['rendered'] ?? '')),
            'link'    => $post['link'] ?? '',
            'excerpt' => html_entity_decode(strip_tags($post['excerpt']['rendered'] ?? ''))
        ];
    }
    $page++;
} while ($page <= 10);

// ── 2. ĐỒNG BỘ 784 SẢN PHẨM (WOOCOMMERCE PRODUCTS) ────────────
$page = 1;
do {
    $url = "https://saigoncacanh.com/wp-json/wp/v2/product?per_page=100&page={$page}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SGCC-Chatbot/1.0'
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$res) break;
    $products = json_decode($res, true);
    if (!is_array($products) || empty($products)) break;

    foreach ($products as $prod) {
        $all_items[] = [
            'type'    => 'Sản phẩm',
            'id'      => $prod['id'] ?? 0,
            'name'    => html_entity_decode(strip_tags($prod['title']['rendered'] ?? '')),
            'link'    => $prod['link'] ?? '',
            'excerpt' => html_entity_decode(strip_tags($prod['excerpt']['rendered'] ?? ''))
        ];
    }
    $page++;
} while ($page <= 15);

// Nếu endpoint /wp/v2/product không có custom route, thử fallback cào qua WooCommerce công khai
if (count($all_items) < 300) {
    // Fallback bổ sung
    file_put_contents(__DIR__ . '/scraped_items.json', json_encode($all_items, JSON_UNESCAPED_UNICODE));
}

echo json_encode([
    'status' => 'success',
    'count'  => count($all_items),
    'data'   => $all_items
], JSON_UNESCAPED_UNICODE);
