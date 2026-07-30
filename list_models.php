<?php
$key_file = __DIR__ . '/gemini_key.txt';
if (!file_exists($key_file)) die("No key");
$api_key = trim(file_get_contents($key_file));

$models = [];
$pageToken = "";
do {
    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($api_key);
    if ($pageToken) $url .= "&pageToken=" . urlencode($pageToken);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (isset($response['models'])) {
        foreach($response['models'] as $m) {
            $models[] = $m['name'];
        }
    }
    $pageToken = $response['nextPageToken'] ?? "";
} while ($pageToken);

echo json_encode($models, JSON_PRETTY_PRINT);
