<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$qa_file = __DIR__ . '/qa_pairs.json';
$unanswered_file = __DIR__ . '/unanswered_questions.json';
$temp_logs_file = __DIR__ . '/temp_logs.json';

$qa_list = file_exists($qa_file) ? json_decode(file_get_contents($qa_file), true) : [];
if (!is_array($qa_list)) $qa_list = [];

$unanswered = file_exists($unanswered_file) ? json_decode(file_get_contents($unanswered_file), true) : [];
if (!is_array($unanswered)) $unanswered = [];

$learned_count = 0;
$new_topics = [];

// 1. Tự động phân tích các câu hỏi chưa được trả lời trong nhật ký chat
if (!empty($unanswered)) {
    foreach ($unanswered as $u) {
        $q_text = is_string($u) ? trim($u) : (isset($u['question']) ? trim($u['question']) : '');
        if (mb_strlen($q_text, 'UTF-8') >= 3) {
            // Kiểm tra xem đã có trong kho Q&A chưa
            $exists = false;
            foreach ($qa_list as $qa) {
                if (mb_strtolower($qa['question'], 'UTF-8') === mb_strtolower($q_text, 'UTF-8')) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                // Tự động tổng hợp câu trả lời thông minh từ câu trả lời của AI hoặc mặc định
                $auto_ans = is_array($u) && !empty($u['answer']) ? $u['answer'] : "Dạ tiệm Sài Gòn Cá Cảnh (246 Hồ Văn Huê, P. Đức Nhuận, Phú Nhuận) xin ghi nhận thắc mắc của anh/chị ạ!\n- Sản phẩm & Dịch vụ: Mời anh/chị xem chi tiết trên [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com) ạ!\n- Hỗ trợ trực tiếp: Anh/chị ghé xem tiệm từ 8h00 - 21h00 các ngày trong tuần nhé ạ!";
                
                array_unshift($qa_list, [
                    'question' => $q_text,
                    'answer' => $auto_ans,
                    'auto_learned' => true,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $learned_count++;
                $new_topics[] = $q_text;
            }
        }
    }
}

// Lưu lại kho QA đã được nâng cấp
file_put_contents($qa_file, json_encode($qa_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Dọn dẹp câu hỏi chưa trả lời sau khi đã nạp
file_put_contents($unanswered_file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'success' => true,
    'learned_count' => $learned_count,
    'total_topics' => count($qa_list),
    'new_topics' => $new_topics,
    'message' => "Đã tự động học và nâng cấp thêm {$learned_count} chủ đề tri thức mới thành công!"
], JSON_UNESCAPED_UNICODE);
