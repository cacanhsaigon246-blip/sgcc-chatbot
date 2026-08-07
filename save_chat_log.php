<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

function isValidVietnamesePhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    if (strpos($phone, '84') === 0 && strlen($phone) === 11) {
        $phone = '0' . substr($phone, 2);
    }
    if (strlen($phone) !== 10) return false;
    if (!preg_match('/^(03|05|07|08|09)/', $phone)) return false;
    if (preg_match('/^(\d)\1{9}$/', $phone)) return false; // Lọc số rác như 0999999999 hoặc 1111111111
    return $phone;
}

function isValidAddress($address) {
    $address = trim($address);
    if (mb_strlen($address, 'UTF-8') < 6) return false;
    if (preg_match('/^(abc|123|test|nhà|ở đây|không có|rác|không biết)/i', $address)) return false;
    return true;
}
$user = isset($input['user']) ? trim($input['user']) : 'Khách vãng lai';
$question = isset($input['question']) ? trim($input['question']) : '';
$answer = isset($input['answer']) ? trim($input['answer']) : '';
$is_fallback = isset($input['isFallback']) ? (bool)$input['isFallback'] : false;
$session_id = isset($input['sessionId']) ? trim($input['sessionId']) : 'unknown';
$user_agent = isset($input['userAgent']) ? trim($input['userAgent']) : 'unknown';

if (empty($question)) {
    echo json_encode(['status' => 'error', 'message' => 'Câu hỏi rỗng']);
    exit;
}

$now = date('Y-m-d H:i:s');
$time_str = date('H:i d/m/Y');

$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
$city = 'Không rõ';
if ($client_ip && $client_ip !== '127.0.0.1' && $client_ip !== '::1') {
    $geo_res = @file_get_contents("http://ip-api.com/json/" . $client_ip . "?fields=status,city");
    if ($geo_res) {
        $geo_data = json_decode($geo_res, true);
        if (isset($geo_data['status']) && $geo_data['status'] === 'success') {
            $city = $geo_data['city'] ?? 'Không rõ';
        }
    }
}
if (stripos($city, 'Minh') !== false || stripos($city, 'HCM') !== false) $city = 'TP.HCM';
else if (stripos($city, 'Hanoi') !== false) $city = 'Hà Nội';
else if (stripos($city, 'Da Nang') !== false) $city = 'Đà Nẵng';

$raw_user = $user; // Giữ lại tên thô của người dùng

// A. Phân tích trích xuất Số điện thoại từ tin nhắn khách
$detected_phone = '';
if (preg_match('/(0[1-9]\d{8})/', $question, $matches)) {
    $detected_phone = isValidVietnamesePhone($matches[1]) ?: '';
}

// B. Phân tích trích xuất Địa chỉ cụ thể từ tin nhắn khách
$detected_address = '';
if (preg_match('/(?:địa chỉ|ship đến|ở tại|địa chỉ là|giao đến)\s*[:|-]?\s*([^,\n\.\?]{6,100})/ui', $question, $addr_matches)) {
    $raw_addr = trim($addr_matches[1]);
    $detected_address = isValidAddress($raw_addr) ? $raw_addr : '';
}

// C. Nhận dạng Tỉnh/Thành từ các từ khóa trong tin nhắn
$detected_city = '';
$cities_list = ['hồ chí minh', 'sài gòn', 'hcm', 'hà nội', 'đà nẵng', 'cần thơ', 'bình dương', 'đồng nai', 'vũng tàu', 'nha trang', 'hải phòng'];
foreach ($cities_list as $c) {
    if (stripos($question, $c) !== false) {
        $detected_city = mb_convert_case($c, MB_CASE_TITLE, 'UTF-8');
        if ($c === 'hcm' || $c === 'sài gòn' || $c === 'hồ chí minh') $detected_city = 'TP.HCM';
        break;
    }
}

// D. Cập nhật thông tin vào members.json làm Lead
if ($detected_phone || $detected_address || $detected_city) {
    $members_file = __DIR__ . '/members.json';
    $members_data = [];
    if (file_exists($members_file)) {
        $members_data = json_decode(file_get_contents($members_file), true) ?: [];
    }
    
    // Nếu là khách vãng lai để lại sđt, đổi tên định danh cho chuyên nghiệp
    $lead_name = ($raw_user === 'Khách vãng lai') ? 'Khách vãng lai (' . ($detected_phone ?: 'Lead') . ')' : $raw_user;
    
    $found_lead = false;
    foreach ($members_data as &$m) {
        if ($m['name'] === $lead_name || ($detected_phone && isset($m['phone']) && $m['phone'] === $detected_phone)) {
            if ($detected_phone) $m['phone'] = $detected_phone;
            if ($detected_address) $m['address'] = $detected_address;
            if ($detected_city) $m['location'] = $detected_city;
            $m['last_active'] = $now;
            $found_lead = true;
            break;
        }
    }
    
    if (!$found_lead) {
        $members_data[] = [
            'name' => $lead_name,
            'phone' => $detected_phone ?: '',
            'address' => $detected_address ?: '',
            'location' => $detected_city ?: $city,
            'first_seen' => $now,
            'last_active' => $now
        ];
    }
    file_put_contents($members_file, json_encode($members_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$user = $user . ' (' . $city . ')';

// 1. Lưu vào nhật ký chat chung
$log_file = __DIR__ . '/server_chat_logs.json';
$logs = [];
if (file_exists($log_file)) {
    $logs = json_decode(file_get_contents($log_file), true) ?: [];
}

// Giới hạn độ dài câu trả lời trong log cho gọn nhẹ
$short_answer = mb_strlen($answer, 'UTF-8') > 150 ? mb_substr($answer, 0, 150, 'UTF-8') . '...' : $answer;

array_unshift($logs, [
    'time' => $time_str,
    'session_id' => $session_id,
    'ip_address' => $client_ip,
    'user_agent' => $user_agent,
    'user' => $user,
    'question' => $question,
    'answer' => $short_answer
]);

if (count($logs) > 500) {
    array_pop($logs); // Giữ tối đa 500 log gần nhất trên server
}
file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 2. Tự động lưu câu hỏi thực tế của khách hàng vào kho câu hỏi chờ nâng cấp tri thức
$unanswered_file = __DIR__ . '/unanswered_questions.json';
$unanswered = file_exists($unanswered_file) ? json_decode(file_get_contents($unanswered_file), true) : [];
if (!is_array($unanswered)) $unanswered = [];

$clean_q = mb_strtolower(trim($question), 'UTF-8');
if (mb_strlen($clean_q, 'UTF-8') >= 3) {
    $found = false;
    foreach ($unanswered as &$item) {
        $q_item = is_string($item) ? mb_strtolower(trim($item), 'UTF-8') : (isset($item['question']) ? mb_strtolower(trim($item['question']), 'UTF-8') : '');
        if ($q_item === $clean_q) {
            if (is_array($item)) {
                $item['count'] = ($item['count'] ?? 1) + 1;
                $item['last_asked'] = $now;
            }
            $found = true;
            break;
        }
    }

    if (!$found) {
        $unanswered[] = [
            'question' => $question,
            'answer' => $answer,
            'count' => 1,
            'last_asked' => $now
        ];
    }

    file_put_contents($unanswered_file, json_encode($unanswered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode(['status' => 'ok']);
