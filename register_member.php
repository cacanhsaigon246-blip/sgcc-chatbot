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

// Cập nhật last_active nếu trùng tên
foreach ($members as &$m) {
    if ($m['name'] === $name) {
        $m['last_active'] = $now;
        $m['location'] = $city;
        $found = true;
        break;
    }
}

if (!$found) {
    $members[] = [
        'name' => $name,
        'location' => $city,
        'first_seen' => $now,
        'last_active' => $now
    ];
}

file_put_contents($file, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['status' => 'ok', 'members' => $members]);
