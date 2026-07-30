<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/qa_pairs.json';
$qa = [];

if (file_exists($file)) {
    $qa = json_decode(file_get_contents($file), true) ?: [];
}

echo json_encode(['status' => 'ok', 'qa' => $qa]);
