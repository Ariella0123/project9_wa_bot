<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// web/api/index.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');
$pdo->exec("SET time_zone = '+08:00'");

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

function sendJson($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Bearer Token 鉴权
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    sendJson(false, 'Unauthorized access', null, 401);
}
$rawToken = $matches[1];
$tokenHash = hash('sha256', $rawToken);

$stmt = $pdo->prepare("SELECT * FROM workers WHERE token_hash = ? AND status != 'disabled'");
$stmt->execute([$tokenHash]);
$worker = $stmt->fetch();

if (!$worker) {
    sendJson(false, 'Invalid or disabled worker token', null, 403);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'heartbeat':
        $waStatus = $_POST['whatsapp_status'] ?? 'disconnected';
        $updateStmt = $pdo->prepare("UPDATE workers SET status = 'online', whatsapp_status = ?, last_seen = NOW() WHERE id = ?");
        $updateStmt->execute([$waStatus, $worker['id']]);
        sendJson(true, 'Heartbeat recorded');
        break;

    case 'claim_job':
        // 关键点：数据库行锁实现 safe atomic claim
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                SELECT id, recipient, message 
                FROM message_jobs 
                WHERE status = 'pending' AND scheduled_at <= NOW() 
                ORDER BY id ASC LIMIT 1 FOR UPDATE
            ");
            $stmt->execute();
            $job = $stmt->fetch();

            if ($job) {
                $claimStmt = $pdo->prepare("
                    UPDATE message_jobs 
                    SET status = 'processing', worker_id = ?, claimed_at = NOW(), attempts = attempts + 1 
                    WHERE id = ?
                ");
                $claimStmt->execute([$worker['id'], $job['id']]);
                $pdo->commit();
                sendJson(true, 'Job claimed', ['job' => $job]);
            } else {
                $pdo->commit();
                sendJson(true, 'No pending jobs', null);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            sendJson(false, 'Failed to claim job: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'report_result':
        $jobId = $_POST['job_id'] ?? 0;
        $status = $_POST['status'] ?? ''; // 'sent' or 'failed'
        $errorInfo = $_POST['error_info'] ?? null;

        // 强校验 worker_id == job.worker_id，防止误报跨界
        $stmt = $pdo->prepare("SELECT worker_id, status FROM message_jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $currentJob = $stmt->fetch();

        if (!$currentJob || $currentJob['worker_id'] != $worker['id']) {
            sendJson(false, 'Unauthorized report attempt for this job', null, 403);
        }

        if ($status === 'sent') {
            $update = $pdo->prepare("UPDATE message_jobs SET status = 'sent', completed_at = NOW() WHERE id = ?");
            $update->execute([$jobId]);
        } else {
            $update = $pdo->prepare("UPDATE message_jobs SET status = 'pending', error_info = ? WHERE id = ?");
            $update->execute([$errorInfo, $jobId]);
        }
        sendJson(true, 'Job result recorded');
        break;

    default:
        sendJson(false, 'Invalid API action', null, 400);
}   