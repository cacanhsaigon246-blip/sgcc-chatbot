<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$question = isset($input['question']) ? trim($input['question']) : '';
$pass = isset($input['pass']) ? $input['pass'] : '';

if ($pass !== 'sgcacanh2024') {
    echo json_encode(['status' => 'error', 'message' => 'Mật khẩu quản trị không đúng']);
    exit;
}

if (empty($question)) {
    echo json_encode(['status' => 'error', 'message' => 'Câu hỏi không hợp lệ']);
    exit;
}

$file = __DIR__ . '/unanswered_questions.json';
$questions = [];

if (file_exists($file)) {
    $questions = json_decode(file_get_contents($file), true) ?: [];
}

$clean_q = mb_strtolower($question, 'UTF-8');
$updated = [];

foreach ($questions as $item) {
    if (mb_strtolower($item['question'], 'UTF-8') !== $clean_q) {
        $updated[] = $item;
    }
}

file_put_contents($file, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['status' => 'ok', 'questions' => $updated]);
