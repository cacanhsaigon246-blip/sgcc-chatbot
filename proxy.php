<?php
/**
 * proxy.php — Gemini API Proxy & Fallback Engine
 * Sài Gòn Cá Cảnh Chatbot
 *
 * File này ẩn API Key, nhận Key từ Header/Payload nếu có,
 * hoặc dùng Key mặc định trên Server.
 */

// ── CORS HEADERS ──────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Gemini-Key');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight & health check
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' || isset($_GET['health'])) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'PHP Proxy Server Online', 'time' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── RATE LIMITING ─────────────────────────────────────────────
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$ip = explode(',', $ip)[0];
$rate_file = sys_get_temp_dir() . '/sgcc_rate_' . md5($ip) . '.json';

$rate_data = ['count' => 0, 'reset_at' => time() + 3600];
if (file_exists($rate_file)) {
    $stored = json_decode(file_get_contents($rate_file), true);
    if ($stored && $stored['reset_at'] > time()) {
        $rate_data = $stored;
    }
}

if ($rate_data['count'] >= 300) { // 300 req/hour/IP
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

$rate_data['count']++;
file_put_contents($rate_file, json_encode($rate_data));

// ── PARSE REQUEST ─────────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

// Nhận Key từ Header hoặc Body
$api_key = $_SERVER['HTTP_X_GEMINI_KEY'] ?? ($data['apiKey'] ?? '');
if (empty($api_key) || strpos($api_key, 'AQ.') === 0) {
    // Nếu chưa có key hợp lệ, kiểm tra file local key
    $key_file = __DIR__ . '/gemini_key.txt';
    if (file_exists($key_file)) {
        $api_key = trim(file_get_contents($key_file));
    }
}

$model = $data['model'] ?? 'gemini-1.5-flash-latest';

// Bỏ apiKey khỏi payload gửi cho Gemini
if (isset($data['apiKey'])) unset($data['apiKey']);

if (empty($api_key)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Chưa cấu hình Gemini API Key chính thức! Anh Phát hãy nhập Key tại https://chatbot.saigoncacanh.com/admin.html mục API Key nhé ạ!'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── CALL GEMINI API ───────────────────────────────────────────
$gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);

$ch = curl_init($gemini_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi kết nối mạng: ' . $curl_error], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code($http_code);
echo $response;
