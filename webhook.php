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

// Jika diakses langsung via browser (GET), tampilkan info status
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'status'  => 'online',
        'message' => 'MBB GitHub Auto-Deploy Webhook is ACTIVE and READY.',
        'time'    => date('Y-m-d H:i:s'),
        'branch'  => 'main',
        'repo'    => 'goldhunter240819-create/cvmbb'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Catat log request
$rawPayload = file_get_contents('php://input');
$logHeader = "[" . date('Y-m-d H:i:s') . "] Webhook Trigger: Event=" . ($event ?: 'unknown') . "\n";

// Respon untuk Event PING dari GitHub
if ($event === 'ping' || empty($rawPayload)) {
    file_put_contents(__DIR__ . '/webhook.log', $logHeader . "Ping successfully acknowledged.\n--------------------------------------------------\n", FILE_APPEND);
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Ping event received successfully from GitHub!'], JSON_PRETTY_PRINT);
    exit;
}

// Jalankan git pull origin main secara aman (TIDAK MENYENTUH DATABASE LIVE SAMA SEKALI)
$gitCmd = 'git pull origin main 2>&1';
$outputStr = shell_exec($gitCmd);

$logEntry = $logHeader . "Git Output:\n" . $outputStr . "\n--------------------------------------------------\n";
file_put_contents(__DIR__ . '/webhook.log', $logEntry, FILE_APPEND);

http_response_code(200);
echo json_encode([
    'status'     => 'success',
    'message'    => 'Git Pull completed successfully. Database live tetap aman & utuh.',
    'timestamp'  => date('Y-m-d H:i:s'),
    'git_output' => trim($outputStr ?? '')
], JSON_PRETTY_PRINT);
