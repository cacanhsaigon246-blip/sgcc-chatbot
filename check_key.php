<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$key_file = __DIR__ . '/gemini_key.txt';
if (file_exists($key_file) && !empty(trim(file_get_contents($key_file)))) {
    echo json_encode(['status' => 'ok', 'hasKey' => true]);
} else {
    echo json_encode(['status' => 'ok', 'hasKey' => false]);
}
