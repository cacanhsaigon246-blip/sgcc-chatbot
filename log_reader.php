<?php
header('Content-Type: text/plain; charset=utf-8');

$log_file = realpath(__DIR__ . '/../error_log');
if ($log_file && file_exists($log_file)) {
    echo "--- Last 50 lines of error_log ---\n";
    $lines = file($log_file);
    $start = max(0, count($lines) - 50);
    for ($i = $start; $i < count($lines); $i++) {
        echo $lines[$i];
    }
} else {
    echo "error_log not found at: " . __DIR__ . '/../error_log';
}
