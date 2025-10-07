<?php
require_once 'config.php';
require_once 'functions.php';

// 记录登出日志
if (isset($_SESSION['admin_id'])) {
    logAction('logout', '管理员退出系统');
}

// 清除会话
session_destroy();

// 重定向到登录页
header('Location: index.php');
exit;
?>



