<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/server_chat_logs.json';
$logs = [];

if (file_exists($file)) {
    $logs = json_decode(file_get_contents($file), true) ?: [];
}

echo json_encode(['status' => 'ok', 'logs' => $logs]);
