<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$name = isset($input['name']) ? trim($input['name']) : (isset($_POST['name']) ? trim($_POST['name']) : (isset($_GET['name']) ? trim($_GET['name']) : ''));

if (empty($name)) {
    echo json_encode(['status' => 'error', 'message' => 'Tên không hợp lệ']);
    exit;
}

$file = __DIR__ . '/members.json';
$members = [];

if (file_exists($file)) {
    $members = json_decode(file_get_contents($file), true) ?: [];
}

$now = date('Y-m-d H:i:s');
$found = false;

// Cập nhật last_active nếu trùng tên
foreach ($members as &$m) {
    if ($m['name'] === $name) {
        $m['last_active'] = $now;
        $found = true;
        break;
    }
}

if (!$found) {
    $members[] = [
        'name' => $name,
        'first_seen' => $now,
        'last_active' => $now
    ];
}

file_put_contents($file, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['status' => 'ok', 'members' => $members]);
