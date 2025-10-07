<?php
// 配置文件
define('DB_FILE', __DIR__ . '/kamika.db');
define('SITE_NAME', '卡密管理系统');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('SESSION_NAME', 'kamika_session');
define('ENCRYPTION_KEY', bin2hex(random_bytes(32))); // 在首次运行时生成

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 错误报告（生产环境请关闭）
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// 会话配置
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // HTTPS环境请设置为1

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// API设置
define('API_TOKEN_EXPIRE', 3600 * 24 * 30); // API Key30天过期
define('CARD_PREFIX', 'KM'); // 卡密前缀

// 分页设置
define('PAGE_SIZE', 20);
?>



