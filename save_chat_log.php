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
