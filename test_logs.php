<?php
$logs = json_decode(file_get_contents('temp_logs.json'), true);
if (!$logs) {
    echo "Could not parse temp_logs.json";
    exit;
}
$recent = array_slice($logs, -20);
foreach($recent as $l) {
    echo "USER: " . ($l['user'] ?? 'Unknown') . "\n";
    echo "Q: " . ($l['question'] ?? '') . "\n";
    echo "A: " . ($l['answer'] ?? '') . "\n";
    echo "--------------------------\n";
}
