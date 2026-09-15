<?php
// web/settings.php

// 1. 修正路径引入：直接引入同级或子目录下的 includes/db.php
require_once __DIR__ . '/includes/db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiUrl = trim($_POST['api_base_url']);
    $timezone = trim($_POST['app_timezone']);

    // 优化 SQL 操作，确保两条 key 明确更新
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('api_base_url', ?), ('app_timezone', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$apiUrl, $timezone]);
    $msg = "Configurations updated successfully!";
}

$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Suite - System Settings</title>
    <!-- 2. 修正 CSS 引用相对路径 -->
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>📱 WhatsApp Suite</h2>
        <!-- 3. 修正 Sidebar 内部相对跳转链接 -->
        <a href="index.php" class="nav-item">📊 Queue Monitor</a>
        <a href="workers.php" class="nav-item">🔑 API Management</a>
        <a href="settings.php" class="nav-item active">⚙️ System Settings</a>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 style="margin:0;">System Configurations</h1>
                <small style="color:#64748b;">Manage global environment configurations and core endpoints.</small>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="card" style="background:#dcfce7; color:#15803d; border-color:#86efac;"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="card" style="max-width:600px;">
            <form method="POST">
                <div style="margin-bottom:16px;">
                    <label style="font-weight:600; font-size:0.875rem; display:block; margin-bottom:6px;">API Base URL Endpoint</label>
                    <input type="text" name="api_base_url" value="<?= htmlspecialchars($settingsRaw['api_base_url'] ?? '') ?>" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-weight:600; font-size:0.875rem; display:block; margin-bottom:6px;">Application Timezone</label>
                    <input type="text" name="app_timezone" value="<?= htmlspecialchars($settingsRaw['app_timezone'] ?? 'Asia/Kuala_Lumpur') ?>" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>