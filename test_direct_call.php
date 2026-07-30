<?php
$proxy_url = 'https://chatbot.saigoncacanh.com/proxy.php';

$payload = json_encode([
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => "Bạn là chuyên gia cá cảnh...\n\nCâu hỏi của khách: xin chao"]
            ]
        ]
    ]
]);

$ch = curl_init($proxy_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
