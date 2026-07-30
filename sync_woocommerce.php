<?php
/**
 * sync_woocommerce.php — Đồng bộ có chọn lọc: type=posts hoặc type=products
 * Sài Gòn Cá Cảnh Chatbot Engine
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$all_items = [];
$posts_count = 0;
$products_count = 0;

// ── 1. ĐỒNG BỘ BÀI VIẾT (POSTS) ───────────────────────────
if ($type === 'posts' || $type === 'all') {
    $page = 1;
    $posts_data = [];
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
            $item = [
                'type'    => 'Bài viết',
                'id'      => $post['id'] ?? 0,
                'name'    => html_entity_decode(strip_tags($post['title']['rendered'] ?? '')),
                'link'    => $post['link'] ?? '',
                'excerpt' => html_entity_decode(strip_tags($post['excerpt']['rendered'] ?? ''))
            ];
            $posts_data[] = $item;
            $all_items[] = $item;
        }
        $page++;
    } while ($page <= 10);
    
    $posts_count = count($posts_data);
    file_put_contents(__DIR__ . '/wordpress_posts.json', json_encode($posts_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ── 2. ĐỒNG BỘ SẢN PHẨM (WOOCOMMERCE PRODUCTS) ────────────
if ($type === 'products' || $type === 'all') {
    $page = 1;
    $products_data = [];
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
            $item = [
                'type'    => 'Sản phẩm',
                'id'      => $prod['id'] ?? 0,
                'name'    => html_entity_decode(strip_tags($prod['title']['rendered'] ?? '')),
                'link'    => $prod['link'] ?? '',
                'excerpt' => html_entity_decode(strip_tags($prod['excerpt']['rendered'] ?? ''))
            ];
            $products_data[] = $item;
            $all_items[] = $item;
        }
        $page++;
    } while ($page <= 15);
    
    $products_count = count($products_data);
    file_put_contents(__DIR__ . '/woocommerce_products.json', json_encode($products_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode([
    'status' => 'success',
    'type' => $type,
    'posts_count' => $posts_count,
    'products_count' => $products_count,
    'total_count' => count($all_items)
], JSON_UNESCAPED_UNICODE);
