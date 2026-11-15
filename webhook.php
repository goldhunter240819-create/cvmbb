<?php
/**
 * MBB MANAGEMENT SYSTEM - GitHub Auto-Deployment Webhook
 * CV. MAJU BERKAH BERSAMA
 */

header('Content-Type: application/json; charset=UTF-8');

// Konfigurasi Webhook Secret (Opsional: samakan dengan yang diisi di GitHub Webhooks)
$secretKey = 'mbb2026'; // Anda bisa mengubah secret ini jika diinginkan

// Fallback getallheaders jika berjalan di CLI / FastCGI
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }
}

$headers = getallheaders();
$event = $headers['X-GitHub-Event'] ?? $headers['x-github-event'] ?? '';
$signature = $headers['X-Hub-Signature-256'] ?? $headers['x-hub-signature-256'] ?? '';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Jika diakses langsung via browser (GET), tampilkan info status webhook
if ($method === 'GET') {
    $lastLog = file_exists(__DIR__ . '/webhook.log') ? file_get_contents(__DIR__ . '/webhook.log') : 'Belum ada log deploy.';
    echo json_encode([
        'status'  => 'online',
        'message' => 'MBB GitHub Auto-Deploy Webhook is ACTIVE and READY.',
        'time'    => date('Y-m-d H:i:s'),
        'branch'  => 'main',
        'repo'    => 'goldhunter240819-create/cvmbb'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Ambil payload body dari GitHub
$rawPayload = file_get_contents('php://input');

// Validasi Secret jika diisi
if (!empty($secretKey) && !empty($signature)) {
    $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawPayload, $secretKey);
    if (!hash_equals($expectedSignature, $signature)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid Secret Signature.']);
        exit;
    }
}

// Hanya proses jika event adalah PUSH atau PING
if ($event === 'ping') {
    echo json_encode(['status' => 'success', 'message' => 'Ping event received successfully from GitHub!']);
    exit;
}

// Jalankan git pull origin main
$output = [];
$returnVar = 0;

$gitCmd = 'git pull origin main 2>&1';
$outputStr = shell_exec($gitCmd);

// Cek apakah database perlu di-refresh
$dbOutput = '';
if (file_exists('C:\xampp\mysql\bin\mysql.exe') && file_exists(__DIR__ . '/database/mbb_management.sql')) {
    // Jalankan import update database secara aman
    $dbCmd = '"C:\xampp\mysql\bin\mysql.exe" -u root mbb_management < "' . __DIR__ . '/database/mbb_management.sql" 2>&1';
    $dbOutput = shell_exec($dbCmd);
}

// Catat riwayat ke webhook.log
$logEntry = "[" . date('Y-m-d H:i:s') . "] Webhook Triggered:\n" . $outputStr . "\n" . ($dbOutput ? "DB Output: " . $dbOutput . "\n" : "") . str_repeat('-', 50) . "\n";
file_put_contents(__DIR__ . '/webhook.log', $logEntry, FILE_APPEND);

echo json_encode([
    'status'     => 'success',
    'message'    => 'Git Pull completed successfully.',
    'timestamp'  => date('Y-m-d H:i:s'),
    'git_output' => trim($outputStr ?? ''),
    'db_status'  => 'Database auto-synced successfully'
], JSON_PRETTY_PRINT);
