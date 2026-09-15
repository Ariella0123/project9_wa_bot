<?php
// web/workers.php

// 1. 修正路径引入：引入同级或子目录下的 includes/db.php
require_once __DIR__ . '/includes/db.php';

$generatedToken = null;

// 创建 Worker Token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_worker') {
    $name = trim($_POST['worker_name']);
    if ($name) {
        $rawToken = bin2hex(random_bytes(16)); // 原生明文 Token 仅展示一次
        $hash = hash('sha256', $rawToken);

        $stmt = $pdo->prepare("INSERT INTO workers (worker_name, token_hash) VALUES (?, ?)");
        $stmt->execute([$name, $hash]);
        $generatedToken = $rawToken;
    }
}

// 吊销 Worker
if (isset($_GET['revoke'])) {
    $id = (int)$_GET['revoke'];
    $stmt = $pdo->prepare("UPDATE workers SET status = 'disabled' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: workers.php");
    exit;
}

$workers = $pdo->query("SELECT * FROM workers ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Suite - API Management</title>
    <!-- 2. 修正 CSS 引用相对路径 -->
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>📱 WhatsApp Suite</h2>
        <!-- 3. 修正 Sidebar 内部相对跳转链接 -->
        <a href="index.php" class="nav-item">📊 Queue Monitor</a>
        <a href="workers.php" class="nav-item active">🔑 API Management</a>
        <a href="settings.php" class="nav-item">⚙️ System Settings</a>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 style="margin:0;">Worker & API Management</h1>
                <small style="color:#64748b;">Generate and manage secure authentication tokens for Python worker nodes.</small>
            </div>
        </div>

        <?php if ($generatedToken): ?>
        <div class="card" style="background:#ecfdf5; border-color:#a7f3d0;">
            <h4 style="color:#065f46; margin:0 0 8px 0;">⚠️ Token Generated Successfully!</h4>
            <p style="margin:0 0 8px 0;">Copy this Bearer token now. It will <strong>never</strong> be displayed again:</p>
            <code style="background:#fff; padding:8px 12px; border:1px solid #6ee7b7; border-radius:4px; font-size:1.1rem; font-weight:bold; color:#047857; display:inline-block;"><?= $generatedToken ?></code>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3>Register New Worker Node</h3>
            <form method="POST" style="display:flex; gap:12px; margin-top:12px;">
                <input type="hidden" name="action" value="create_worker">
                <input type="text" name="worker_name" placeholder="Worker Name (e.g. Office PC)" required style="max-width:300px;">
                <button type="submit" class="btn btn-primary">+ Generate Worker Token</button>
            </form>
        </div>

        <div class="card" style="padding:0;">
            <h3 style="padding:16px 16px 0 16px; margin:0;">Active Registered Nodes</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>WORKER NAME</th>
                        <th>STATUS</th>
                        <th>LAST SEEN</th>
                        <th>REGISTERED AT</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workers as $w): ?>
                    <tr>
                        <td>#<?= $w['id'] ?></td>
                        <td><strong><?= htmlspecialchars($w['worker_name']) ?></strong></td>
                        <td><span class="badge badge-<?= $w['status'] == 'disabled' ? 'failed' : 'online' ?>">● <?= ucfirst($w['status']) ?></span></td>
                        <td><?= $w['last_seen'] ?: 'Never' ?></td>
                        <td><?= $w['created_at'] ?></td>
                        <td>
                            <?php if ($w['status'] !== 'disabled'): ?>
                                <a href="workers.php?revoke=<?= $w['id'] ?>" class="btn btn-danger" style="padding:4px 8px; font-size:0.75rem;">Revoke</a>
                            <?php else: ?>
                                <span style="color:#94a3b8;">Revoked</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>