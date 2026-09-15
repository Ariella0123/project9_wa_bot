<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. 修正路径引入：直接引入同级或子目录下的 includes/db.php
require_once __DIR__ . '/includes/db.php';

// 处理快速发送请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enqueue') {
    $phone = trim($_POST['phone']);
    $msg = trim($_POST['message']);
    $scheduled = $_POST['scheduled_at'] ?: date('Y-m-d H:i:s');

    if ($phone && $msg) {
        $stmt = $pdo->prepare("INSERT INTO message_jobs (recipient, message, scheduled_at) VALUES (?, ?, ?)");
        $stmt->execute([$phone, $msg, $scheduled]);
        header("Location: index.php");
        exit;
    }
}

// 清理/重试操作
if (isset($_GET['op'])) {
    if ($_GET['op'] === 'retry') {
        $pdo->query("UPDATE message_jobs SET status = 'pending', attempts = 0 WHERE status = 'failed'");
    } elseif ($_GET['op'] === 'clear_failed') {
        $pdo->query("DELETE FROM message_jobs WHERE status = 'failed'");
    }
    header("Location: index.php");
    exit;
}

// 提取统计指标
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(status='pending') as pending,
        SUM(status='processing') as processing,
        SUM(status='sent') as sent,
        SUM(status='failed') as failed
    FROM message_jobs
")->fetch();

// 提取日志列表
$jobs = $pdo->query("SELECT * FROM message_jobs ORDER BY id DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Suite - Queue Monitor</title>
    <!-- 2. 修正 CSS 引用相对路径 -->
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>📱 WhatsApp Suite</h2>
        <!-- 3. 修正 Sidebar 内部相对跳转链接 -->
        <a href="index.php" class="nav-item active">📊 Queue Monitor</a>
        <a href="workers.php" class="nav-item">🔑 API Management</a>
        <a href="settings.php" class="nav-item">⚙️ System Settings</a>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 style="margin:0;">Message Queue & Dispatch Control</h1>
                <small style="color:#64748b;">Live automated monitoring and quick queue control</small>
            </div>
            <div>
                Worker Status: <span style="color:#10b981; font-weight:bold;">● Operational</span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="label">TOTAL JOBS</div><div class="val"><?= $stats['total'] ?></div></div>
            <div class="stat-card"><div class="label" style="color:#d97706;">PENDING</div><div class="val" style="color:#d97706;"><?= $stats['pending'] ?: 0 ?></div></div>
            <div class="stat-card"><div class="label" style="color:#2563eb;">PROCESSING</div><div class="val" style="color:#2563eb;"><?= $stats['processing'] ?: 0 ?></div></div>
            <div class="stat-card"><div class="label" style="color:#059669;">SENT</div><div class="val" style="color:#059669;"><?= $stats['sent'] ?: 0 ?></div></div>
            <div class="stat-card"><div class="label" style="color:#dc2626;">FAILED</div><div class="val" style="color:#dc2626;"><?= $stats['failed'] ?: 0 ?></div></div>
        </div>

        <div class="card">
            <form method="POST" style="display:flex; gap:12px; align-items:center;">
                <input type="hidden" name="action" value="enqueue">
                <input type="text" name="phone" placeholder="Recipient Phone (e.g. 6012345...)" required style="flex:1;">
                <input type="datetime-local" name="scheduled_at" style="flex:1;">
                <textarea name="message" placeholder="Type quick message body..." rows="1" required style="flex:2;"></textarea>
                <button type="submit" class="btn btn-primary">+ Queue Message</button>
            </form>
        </div>

        <div style="margin-bottom: 16px;">
            <a href="index.php?op=retry" class="btn btn-blue">🔄 Retry All Failed</a>
            <a href="index.php?op=clear_failed" class="btn btn-danger">🗑️ Clear Failed Logs</a>
        </div>

        <div class="card" style="padding:0;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>RECIPIENT</th>
                        <th>MESSAGE</th>
                        <th>STATUS</th>
                        <th>ATTEMPTS</th>
                        <th>SCHEDULED</th>
                        <th>COMPLETED</th>
                        <th>ERROR INFO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td>#<?= $job['id'] ?></td>
                        <td><strong><?= htmlspecialchars($job['recipient']) ?></strong></td>
                        <td><?= htmlspecialchars($job['message']) ?></td>
                        <td><span class="badge badge-<?= $job['status'] ?>"><?= strtoupper($job['status']) ?></span></td>
                        <td><?= $job['attempts'] ?></td>
                        <td><?= $job['scheduled_at'] ?></td>
                        <td><?= $job['completed_at'] ?: '—' ?></td>
                        <td style="color:#dc2626;"><?= htmlspecialchars($job['error_info'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>