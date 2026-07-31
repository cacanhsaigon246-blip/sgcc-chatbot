<?php
/**
 * admin_proxy.php — Trợ lý AI Quản Lý Cửa Hàng (CEO AI Assistant) dành riêng cho Anh Phát
 * Nạp toàn bộ CSDL POS, WooCommerce, Lead khách hàng & Thống kê chat để tra cứu siêu tốc.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Gemini-Key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

$question = $data['question'] ?? '';
if (empty($question)) {
    http_response_code(400);
    echo json_encode(['error' => 'Câu hỏi rỗng!'], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── NẠP TẤT CẢ CƠ SỞ DỮ LIỆU ĐỂ BÁO CÁO CHO ANH PHÁT ───────────────────────

// 1. Kho POS
$pos_file = __DIR__ . '/pos_products.json';
$pos_products = file_exists($pos_file) ? (json_decode(file_get_contents($pos_file), true) ?: []) : [];

// 2. Sản phẩm WooCommerce
$woo_file = __DIR__ . '/woocommerce_products.json';
$woo_products = file_exists($woo_file) ? (json_decode(file_get_contents($woo_file), true) ?: []) : [];

// 3. Lead & Thành viên (SĐT, Địa chỉ)
$members_file = __DIR__ . '/members.json';
$members_data = file_exists($members_file) ? (json_decode(file_get_contents($members_file), true) ?: []) : [];

// 4. Những câu hỏi thiếu / món khách cần mà chưa có trong POS
$unanswered_file = __DIR__ . '/unanswered_questions.json';
$unanswered_data = file_exists($unanswered_file) ? (json_decode(file_get_contents($unanswered_file), true) ?: []) : [];

// 5. Nhật ký chat gần nhất
$logs_file = __DIR__ . '/server_chat_logs.json';
$logs_data = file_exists($logs_file) ? (json_decode(file_get_contents($logs_file), true) ?: []) : [];

// ── XỬ LÝ PHÂN TÍCH TỔNG QUAN TỒN KHO ──────────────────────────────────────
$total_pos = count($pos_products);
$total_woo = count($woo_products);
$total_members = count($members_data);

$out_of_stock_list = [];
$low_stock_list = [];

foreach ($pos_products as $p) {
    $qty = intval($p['qty'] ?? 0);
    if ($qty <= 0) {
        $out_of_stock_list[] = $p['name'] . " (Giá: " . number_format(intval($p['sellPrice'] ?? 0), 0, ',', '.') . "đ)";
    } else if ($qty <= 5) {
        $low_stock_list[] = $p['name'] . " (Còn " . $qty . " sp | Giá: " . number_format(intval($p['sellPrice'] ?? 0), 0, ',', '.') . "đ)";
    }
}

// Tổng hợp ngữ cảnh cho AI
$pos_summary = "[TỔNG QUAN KHO POS THỰC TẾ (pos.saigoncacanh.com)]:\n";
$pos_summary .= "- Tổng số mã sản phẩm trong POS: {$total_pos}\n";
$pos_summary .= "- Số sản phẩm hết hàng (Tồn = 0): " . count($out_of_stock_list) . "\n";
$pos_summary .= "- Số sản phẩm sắp hết hàng (Tồn <= 5): " . count($low_stock_list) . "\n\n";

if (!empty($out_of_stock_list)) {
    $pos_summary .= "Danh sách HẾT HÀNG (Tồn = 0):\n- " . implode("\n- ", array_slice($out_of_stock_list, 0, 15)) . "\n\n";
}
if (!empty($low_stock_list)) {
    $pos_summary .= "Danh sách SẮP HẾT HÀNG (Tồn <= 5):\n- " . implode("\n- ", array_slice($low_stock_list, 0, 15)) . "\n\n";
}

$members_summary = "[DANH SÁCH LEAD KHÁCH HÀNG & SĐT MỚI NHẤT]:\n";
$recent_members = array_slice($members_data, 0, 10);
foreach ($recent_members as $m) {
    $members_summary .= "- " . ($m['name'] ?? 'Khách') . " | SĐT: " . ($m['phone'] ?: 'Chưa để lại') . " | Địa chỉ: " . ($m['address'] ?: 'Chưa có') . " | Vị trí: " . ($m['location'] ?? '—') . "\n";
}

$unanswered_summary = "\n[NHỮNG MÓN / CÂU HỎI KHÁCH ĐANG CẦN MÀ CHƯA CÓ TRONG POS]:\n";
foreach (array_slice($unanswered_data, 0, 10) as $u) {
    $unanswered_summary .= "- \"" . ($u['question'] ?? '') . "\" (Hỏi " . ($u['count'] ?? 1) . " lần)\n";
}

// Nạp toàn bộ danh sách POS cho Gemini tra cứu chính xác
$pos_details = "\n[TOÀN BỘ DANH SÁCH SẢN PHẨM TRONG POS]:\n";
foreach (array_slice($pos_products, 0, 80) as $p) {
    $pos_details .= "- " . ($p['name'] ?? '') . " | Giá: " . number_format(intval($p['sellPrice'] ?? 0), 0, ',', '.') . "đ | Tồn: " . ($p['qty'] ?? 0) . " | Nhóm: " . ($p['category'] ?? '—') . "\n";
}

$system_prompt = "Bạn là TRỢ LÝ AI CAO CẤP DÀNH RIÊNG CHO ANH PHÁT (CHỦ TIỆM SÀI GÒN CÁ CẢNH).

Mục tiêu của bạn:
- Giúp Anh Phát tra cứu thông tin vận hành, kiểm tra kho POS, thống kê đơn hàng và nhu cầu khách hàng một cách NHAU CHÓNG, CHÍNH XÁC VÀ TỔNG QUAN NHẤT.
- Văn phong: Tự nhiên, tôn trọng, chu đáo, gọi 'Anh Phát', xưng 'Em'.
- Trình bày thông tin bằng gạch đầu dòng rõ ràng, dễ nhìn trên cả màn hình điện thoại lẫn máy tính.

DỮ LIỆU HỆ THỐNG HIỆN TẠI:
{$pos_summary}
{$members_summary}
{$unanswered_summary}
{$pos_details}";

// Lấy API Key từ Header, Body hoặc Server local
$api_key = $_SERVER['HTTP_X_GEMINI_KEY'] ?? ($data['apiKey'] ?? '');
if (empty($api_key)) {
    $key_file = __DIR__ . '/gemini_key.txt';
    if (file_exists($key_file)) {
        $api_key = trim(file_get_contents($key_file));
    }
}

if (empty($api_key)) {
    http_response_code(400);
    echo json_encode(['error' => 'Chưa có Gemini API Key trên Server!'], JSON_UNESCAPED_UNICODE);
    exit();
}

$payload = [
    'systemInstruction' => ['parts' => [['text' => $system_prompt]]],
    'contents' => [
        ['role' => 'user', 'parts' => [['text' => $question]]]
    ]
];

$model = 'gemini-1.5-flash';
$gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);

$ch = curl_init($gemini_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($http_code);
echo $response;
