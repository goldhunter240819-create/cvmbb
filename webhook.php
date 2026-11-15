<?php
/**
 * MBB MANAGEMENT SYSTEM - GitHub Auto-Deployment Webhook
 * CV. MAJU BERKAH BERSAMA
 */

header('Content-Type: application/json; charset=UTF-8');

// Deteksi Event dari GitHub
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if (empty($event) && function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        if (strtolower($k) === 'x-github-event') {
            $event = $v;
            break;
        }
    }
}

// Jika diakses langsung via browser (GET), tampilkan status online
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'status'  => 'online',
        'message' => 'MBB GitHub Webhook Endpoint is ACTIVE and READY.',
        'time'    => date('Y-m-d H:i:s'),
        'branch'  => 'main'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Respon Cepat untuk Event PING
if ($event === 'ping') {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Ping acknowledged!'], JSON_PRETTY_PRINT);
    exit;
}

// Jalankan git pull secara non-blocking tanpa menunggu prompt terminal
$gitCmd = 'set GIT_TERMINAL_PROMPT=0 && git pull origin main 2>&1';
$outputStr = shell_exec($gitCmd);

$logEntry = "[" . date('Y-m-d H:i:s') . "] Webhook Trigger (Event: {$event}):\n" . $outputStr . "\n" . str_repeat('-', 50) . "\n";
file_put_contents(__DIR__ . '/webhook.log', $logEntry, FILE_APPEND);

http_response_code(200);
echo json_encode([
    'status'     => 'success',
    'message'    => 'Git Pull processed.',
    'timestamp'  => date('Y-m-d H:i:s'),
    'git_output' => trim($outputStr ?? '')
], JSON_PRETTY_PRINT);
