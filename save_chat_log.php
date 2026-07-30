<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$user = isset($input['user']) ? trim($input['user']) : 'Khách vãng lai';
$question = isset($input['question']) ? trim($input['question']) : '';
$answer = isset($input['answer']) ? trim($input['answer']) : '';
$is_fallback = isset($input['isFallback']) ? (bool)$input['isFallback'] : false;

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
    $detected_phone = $matches[1];
}

// B. Phân tích trích xuất Địa chỉ cụ thể từ tin nhắn khách
$detected_address = '';
if (preg_match('/(?:địa chỉ|ship đến|ở tại|địa chỉ là|giao đến)\s*[:|-]?\s*([^,\n\.\?]{6,100})/ui', $question, $addr_matches)) {
    $detected_address = trim($addr_matches[1]);
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
    'user' => $user,
    'question' => $question,
    'answer' => $short_answer
]);

if (count($logs) > 500) {
    array_pop($logs); // Giữ tối đa 500 log gần nhất trên server
}
file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 2. Nếu là câu trả lời dự phòng (AI chưa biết trả lời), lưu vào danh sách gợi ý chưa trả lời
if ($is_fallback) {
    $unanswered_file = __DIR__ . '/unanswered_questions.json';
    $unanswered = [];
    if (file_exists($unanswered_file)) {
        $unanswered = json_decode(file_get_contents($unanswered_file), true) ?: [];
    }

    $found = false;
    $clean_q = mb_strtolower(trim($question), 'UTF-8');
    foreach ($unanswered as &$item) {
        if (mb_strtolower($item['question'], 'UTF-8') === $clean_q) {
            $item['count'] += 1;
            $item['last_asked'] = $now;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $unanswered[] = [
            'question' => $question,
            'count' => 1,
            'last_asked' => $now
        ];
    }

    file_put_contents($unanswered_file, json_encode($unanswered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode(['status' => 'ok']);
