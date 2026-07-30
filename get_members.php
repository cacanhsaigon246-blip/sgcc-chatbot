<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/members.json';
$members = [];

if (file_exists($file)) {
    $members = json_decode(file_get_contents($file), true) ?: [];
}

echo json_encode(['status' => 'ok', 'members' => $members]);
