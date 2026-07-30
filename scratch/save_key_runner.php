<?php
$url = 'https://chatbot.saigoncacanh.com/save_key.php';
$payload = json_encode([
    'key' => 'AIzaSyBMzaJd9SBcQEvGXXNZFWDMa6kgCFzX5HE',
    'pass' => 'sgcacanh2024'
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
