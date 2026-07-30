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

$model = $data['model'] ?? 'gemini-flash-lite-latest';

// Bỏ apiKey khỏi payload gửi cho Gemini
if (isset($data['apiKey'])) unset($data['apiKey']);

if (empty($api_key)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Chưa cấu hình Gemini API Key chính thức! Anh Phát hãy nhập Key tại https://chatbot.saigoncacanh.com/admin.html mục API Key nhé ạ!'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Tự động chèn Quy tắc Q&A huấn luyện từ Admin Panel vào nội dung câu hỏi
$qa_file = __DIR__ . '/qa_pairs.json';
if (file_exists($qa_file)) {
    $qa_data = json_decode(file_get_contents($qa_file), true);
    if (is_array($qa_data) && !empty($qa_data)) {
        $qa_rules = "\n\n[QUY TẮC TRẢ LỜI QUAN TRỌNG ĐƯỢC CHỈ ĐỊNH BỞI CỬA HÀNG - HÃY TUÂN THỦ TUYỆT ĐỐI]:\n";
        foreach ($qa_data as $qa) {
            if (!empty($qa['question']) && !empty($qa['answer'])) {
                $qa_rules .= "- Khách hỏi: \"" . $qa['question'] . "\"\n  Hãy trả lời đúng như sau: \"" . $qa['answer'] . "\"\n";
            }
        }
        $qa_rules .= "\nNếu câu hỏi của khách hàng trùng khớp hoặc gần giống với các câu hỏi trên, bạn PHẢI sử dụng câu trả lời tương ứng được chỉ định ở trên thay vì tự ý bịa đặt.";
        
        if (isset($data['contents'][0]['parts'][0]['text'])) {
            $data['contents'][0]['parts'][0]['text'] .= $qa_rules;
        }
    }
}

// Tự động định vị Tỉnh/Thành của khách hàng qua IP để chỉ thị AI
$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
$city = 'Không rõ';
if ($client_ip && $client_ip !== '127.0.0.1' && $client_ip !== '::1') {
    // Gọi API định vị nhẹ qua ip-api
    $geo_res = @file_get_contents("http://ip-api.com/json/" . $client_ip . "?fields=status,city");
    if ($geo_res) {
        $geo_data = json_decode($geo_res, true);
        if (isset($geo_data['status']) && $geo_data['status'] === 'success') {
            $city = $geo_data['city'] ?? 'Không rõ';
        }
    }
}

$location_instruction = "";
if ($city === 'Không rõ') {
    $location_instruction = "\n\n[CHỈ THỊ THU THẬP THÔNG TIN]: Vị trí địa lý của khách hàng hiện tại chưa rõ ràng (Không rõ). Hãy tìm thời cơ khéo léo hỏi xem khách ở quận nào của TP.HCM hoặc ở tỉnh nào để tiện báo giá ship chính xác và tư vấn. Khi khách có nhu cầu mua hàng, hãy lịch sự xin Số điện thoại và Địa chỉ chi tiết để shop gọi điện chốt đơn nhanh nhất.";
} else if (stripos($city, 'Minh') !== false || stripos($city, 'HCM') !== false) {
    $location_instruction = "\n\n[CHỈ THỊ ĐỊA LÝ QUAN TRỌNG - KHÁCH Ở TP.HCM]: Khách hàng này đang ở khu vực TP.HCM. Hãy nhiệt tình mời họ ghé thăm trực tiếp cửa hàng tại địa chỉ: 246 Hồ Văn Huê, Phường Đức Nhuận để lựa chọn cá trực tiếp và nhận tư vấn chuyên sâu từ chủ cửa hàng (anh Phát). Khi khách có nhu cầu mua hàng hoặc giao tận nơi, hãy khéo léo xin Số điện thoại và Địa chỉ cụ thể để shop gọi hỗ trợ nhé.";
} else {
    $location_instruction = "\n\n[CHỈ THỊ ĐỊA LÝ QUAN TRỌNG - KHÁCH TỈNH]: Khách hàng này ở ngoài TP.HCM (" . $city . "). Hãy hướng dẫn họ mua sắm Online và tư vấn mua phụ kiện/thuốc chữa bệnh qua Siêu thị Shopee bên em giao hàng nhanh toàn quốc: https://shop.saigoncacanh.com hoặc nhắn Zalo Shop. Khi khách muốn đặt hàng trực tiếp, hãy khéo léo xin Số điện thoại và Địa chỉ giao hàng để shop gọi tư vấn và vận chuyển.";
}

if (isset($data['contents'][0]['parts'][0]['text'])) {
    $data['contents'][0]['parts'][0]['text'] .= $location_instruction;
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
