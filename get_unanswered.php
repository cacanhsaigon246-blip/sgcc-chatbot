<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/unanswered_questions.json';
$questions = [];

if (file_exists($file)) {
    $questions = json_decode(file_get_contents($file), true) ?: [];
}

// Sắp xếp theo tần suất hỏi nhiều nhất (count giảm dần)
usort($questions, function($a, $b) {
    return $b['count'] <=> $a['count'];
});

echo json_encode(['status' => 'ok', 'questions' => $questions]);
