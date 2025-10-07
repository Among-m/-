<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

checkLogin();

$db = Database::getInstance();
$admin = getCurrentAdmin();

// 获取当前页面
$page = $_GET['page'] ?? 'overview';
$currentPage = intval($_GET['p'] ?? 1);

// 处理导出请求（必须在任何HTML输出之前）
if ($page == 'cards' && isset($_GET['action']) && $_GET['action'] == 'export') {
    $projectId = intval($_GET['project_id'] ?? 0);
    $status = $_GET['status'] ?? '';
    $format = $_GET['format'] ?? 'csv';
    $isSuperAdmin = ($admin['role'] === 'super_admin');
    
    $where = '1=1';
    $params = [];
    
    // 非超级管理员只能导出自己项目的卡密
    if (!$isSuperAdmin) {
        $where .= ' AND project_id IN (SELECT id FROM projects WHERE admin_id = ?)';
        $params[] = $admin['id'];
    }
    
    if ($projectId > 0) {
        $where .= ' AND project_id = ?';
        $params[] = $projectId;
    }
    
    if ($status !== '') {
        $where .= ' AND status = ?';
        $params[] = intval($status);
    }
    
    $cards = $db->fetchAll("SELECT * FROM cards WHERE $where ORDER BY id DESC", $params);
    
    // 根据格式导出
    if ($format == 'csv') {
        exportCardsToCSV($cards);
    } elseif ($format == 'txt_simple') {
        exportCardsToTXT($cards, 'simple');
    } elseif ($format == 'txt_detailed') {
        exportCardsToTXT($cards, 'detailed');
    } else {
        exportCardsToCSV($cards);
    }
    exit;
}

// 处理AJAX请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_project':
            $id = intval($_POST['id'] ?? 0);
            if ($db->delete('projects', 'id = ?', [$id])) {
                logAction('delete_project', "删除项目 ID: $id", $id);
                echo json_encode(['success' => true, 'message' => '删除成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '删除失败']);
            }
            exit;
            
        case 'toggle_project_status':
            $id = intval($_POST['id'] ?? 0);
            $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$id]);
            if ($project) {
                $newStatus = $project['status'] == 1 ? 0 : 1;
                $db->update('projects', ['status' => $newStatus], 'id = ?', [$id]);
                logAction('toggle_project_status', "切换项目状态 ID: $id, 新状态: $newStatus", $id);
                echo json_encode(['success' => true, 'status' => $newStatus]);
            }
            exit;
            
        case 'reset_project_token':
            $projectId = intval($_POST['project_id'] ?? 0);
            
            if ($projectId <= 0) {
                echo json_encode(['success' => false, 'message' => '项目ID无效']);
                exit;
            }
            
            // 验证项目是否属于当前管理员
            if ($admin['role'] !== 'super_admin') {
                $project = $db->fetchOne("SELECT * FROM projects WHERE id = ? AND admin_id = ?", [$projectId, $admin['id']]);
                if (!$project) {
                    echo json_encode(['success' => false, 'message' => '无权操作此项目']);
                    exit;
                }
            }
            
            $db->beginTransaction();
            try {
                // 获取项目信息
                $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
                if (!$project) {
                    throw new Exception('项目不存在');
                }
                
                // 删除旧令牌（将状态设置为禁用，而不是直接删除，便于审计）
                $db->update('api_tokens', ['status' => 0], 'project_id = ?', [$projectId]);
                
                // 生成新令牌
                $newToken = generateApiToken($projectId, $project['name'] . ' - 令牌', null);
                
                if (!$newToken) {
                    throw new Exception('生成新令牌失败');
                }
                
                $db->commit();
                
                logAction('reset_project_token', "重置项目令牌: {$project['name']}", $projectId);
                
                echo json_encode([
                    'success' => true,
                    'message' => '令牌重置成功',
                    'token' => $newToken
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'generate_project_token':
            $projectId = intval($_POST['project_id'] ?? 0);
            
            if ($projectId <= 0) {
                echo json_encode(['success' => false, 'message' => '项目ID无效']);
                exit;
            }
            
            // 验证项目是否属于当前管理员
            if ($admin['role'] !== 'super_admin') {
                $project = $db->fetchOne("SELECT * FROM projects WHERE id = ? AND admin_id = ?", [$projectId, $admin['id']]);
                if (!$project) {
                    echo json_encode(['success' => false, 'message' => '无权操作此项目']);
                    exit;
                }
            }
            
            try {
                $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
                if (!$project) {
                    throw new Exception('项目不存在');
                }
                
                // 检查是否已有令牌
                $existToken = $db->fetchOne("SELECT * FROM api_tokens WHERE project_id = ? AND status = 1", [$projectId]);
                if ($existToken) {
                    throw new Exception('该项目已有有效令牌，请使用重置功能');
                }
                
                // 生成新令牌
                $newToken = generateApiToken($projectId, $project['name'] . ' - 令牌', null);
                
                if (!$newToken) {
                    throw new Exception('生成令牌失败');
                }
                
                logAction('generate_project_token', "为项目生成令牌: {$project['name']}", $projectId);
                
                echo json_encode([
                    'success' => true,
                    'message' => '令牌生成成功',
                    'token' => $newToken
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_card':
            $id = intval($_POST['id'] ?? 0);
            if ($db->delete('cards', 'id = ?', [$id])) {
                logAction('delete_card', "删除卡密 ID: $id");
                echo json_encode(['success' => true, 'message' => '删除成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '删除失败']);
            }
            exit;
            
        case 'unbind_card':
            $id = intval($_POST['id'] ?? 0);
            $card = $db->fetchOne("SELECT * FROM cards WHERE id = ?", [$id]);
            if ($card) {
                $db->beginTransaction();
                try {
                    $db->update('cards', [
                        'device_id' => null,
                        'device_model' => null,
                        'status' => 0,
                        'use_count' => 0,
                        'activated_at' => null,
                        'expire_date' => null
                    ], 'id = ?', [$id]);
                    $db->delete('users', 'card_key = ?', [$card['card_key']]);
                    $db->commit();
                    logAction('unbind_card', "解绑卡密: {$card['card_key']}");
                    echo json_encode(['success' => true, 'message' => '解绑成功']);
                } catch (Exception $e) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => '解绑失败']);
                }
            }
            exit;
            
        case 'delete_user':
            $id = intval($_POST['id'] ?? 0);
            if ($db->delete('users', 'id = ?', [$id])) {
                logAction('delete_user', "删除用户 ID: $id");
                echo json_encode(['success' => true, 'message' => '删除成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '删除失败']);
            }
            exit;
            
        case 'delete_admin':
            $id = intval($_POST['id'] ?? 0);
            if ($id == $admin['id']) {
                echo json_encode(['success' => false, 'message' => '不能删除自己']);
            } elseif ($db->delete('admins', 'id = ?', [$id])) {
                logAction('delete_admin', "删除管理员 ID: $id");
                echo json_encode(['success' => true, 'message' => '删除成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '删除失败']);
            }
            exit;
    }
    
    echo json_encode(['success' => false, 'message' => '未知操作']);
    exit;
}

// 处理用户管理的GET请求
if ($page == 'users' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    switch ($action) {
        case 'get_user':
            $id = intval($_GET['id'] ?? 0);
            $user = $db->fetchOne("SELECT * FROM app_users WHERE id = ?", [$id]);
            if ($user) {
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => '用户不存在']);
            }
            exit;
            
        case 'get_user_detail':
            $id = intval($_GET['id'] ?? 0);
            $user = $db->fetchOne("SELECT * FROM app_users WHERE id = ?", [$id]);
            if ($user) {
                // 获取用户绑定的卡密（device_id 字段存储的是 username）
                $user['cards'] = $db->fetchAll("SELECT c.*, p.name as project_name FROM cards c 
                    LEFT JOIN projects p ON c.project_id = p.id 
                    WHERE c.device_id = ? AND c.status = 1 ORDER BY c.activated_at DESC", [$user['username']]);
                $user['card_count'] = count($user['cards']);
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => '用户不存在']);
            }
            exit;
    }
}

// 处理用户管理的POST请求
if ($page == 'users' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'save_user':
            $userId = intval($_POST['user_id'] ?? 0);
            $projectId = intval($_POST['project_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $vipLevel = intval($_POST['vip_level'] ?? 0);
            $balance = floatval($_POST['balance'] ?? 0);
            $status = intval($_POST['status'] ?? 1);
            
            // 验证必填字段
            if (empty($username)) {
                echo json_encode(['success' => false, 'message' => '用户名不能为空']);
                exit;
            }
            
            if ($projectId <= 0) {
                echo json_encode(['success' => false, 'message' => '请选择项目']);
                exit;
            }
            
            // 验证项目是否属于当前管理员
            $project = $db->fetchOne("SELECT * FROM projects WHERE id = ? AND admin_id = ?", [$projectId, $admin['id']]);
            if (!$project) {
                echo json_encode(['success' => false, 'message' => '无效的项目']);
                exit;
            }
            
            // 验证用户名格式
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                echo json_encode(['success' => false, 'message' => '用户名只能包含字母、数字和下划线']);
                exit;
            }
            
            $db->beginTransaction();
            
            try {
                if ($userId > 0) {
                    // 编辑用户
                    $existUser = $db->fetchOne("SELECT * FROM app_users WHERE id = ?", [$userId]);
                    if (!$existUser) {
                        throw new Exception('用户不存在');
                    }
                    
                    // 检查用户名是否被其他用户使用
                    if ($username != $existUser['username']) {
                        $nameExists = $db->fetchOne("SELECT id FROM app_users WHERE username = ? AND id != ?", [$username, $userId]);
                        if ($nameExists) {
                            throw new Exception('用户名已存在');
                        }
                    }
                    
                    // 检查邮箱是否被其他用户使用
                    if ($email && $email != $existUser['email']) {
                        $emailExists = $db->fetchOne("SELECT id FROM app_users WHERE email = ? AND id != ?", [$email, $userId]);
                        if ($emailExists) {
                            throw new Exception('邮箱已被使用');
                        }
                    }
                    
                    // 检查手机号是否被其他用户使用
                    if ($phone && $phone != $existUser['phone']) {
                        $phoneExists = $db->fetchOne("SELECT id FROM app_users WHERE phone = ? AND id != ?", [$phone, $userId]);
                        if ($phoneExists) {
                            throw new Exception('手机号已被使用');
                        }
                    }
                    
                    $updateData = [
                        'project_id' => $projectId,
                        'username' => $username,
                        'email' => $email,
                        'phone' => $phone,
                        'vip_level' => $vipLevel,
                        'balance' => $balance,
                        'status' => $status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    if (!empty($password)) {
                        $updateData['password'] = password_hash($password, PASSWORD_BCRYPT);
                    }
                    
                    $db->update('app_users', $updateData, 'id = ?', [$userId]);
                    logAction('update_app_user', "编辑用户: $username");
                    
                } else {
                    // 添加新用户
                    if (empty($password)) {
                        throw new Exception('密码不能为空');
                    }
                    
                    // 检查用户名是否已存在
                    $exists = $db->fetchOne("SELECT id FROM app_users WHERE username = ?", [$username]);
                    if ($exists) {
                        throw new Exception('用户名已存在');
                    }
                    
                    // 检查邮箱是否已被使用
                    if ($email) {
                        $emailExists = $db->fetchOne("SELECT id FROM app_users WHERE email = ?", [$email]);
                        if ($emailExists) {
                            throw new Exception('邮箱已被使用');
                        }
                    }
                    
                    // 检查手机号是否已被使用
                    if ($phone) {
                        $phoneExists = $db->fetchOne("SELECT id FROM app_users WHERE phone = ?", [$phone]);
                        if ($phoneExists) {
                            throw new Exception('手机号已被使用');
                        }
                    }
                    
                    $insertData = [
                        'project_id' => $projectId,
                        'username' => $username,
                        'password' => password_hash($password, PASSWORD_BCRYPT),
                        'email' => $email,
                        'phone' => $phone,
                        'vip_level' => $vipLevel,
                        'balance' => $balance,
                        'status' => $status,
                        'token' => bin2hex(random_bytes(32)),
                        'token_expire' => date('Y-m-d H:i:s', time() + 86400 * 30)
                    ];
                    
                    $userId = $db->insert('app_users', $insertData);
                    logAction('add_app_user', "添加用户: $username");
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => '保存成功']);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_user':
            $id = intval($_GET['id'] ?? 0);
            $user = $db->fetchOne("SELECT * FROM app_users WHERE id = ?", [$id]);
            if ($user) {
                if ($db->delete('app_users', 'id = ?', [$id])) {
                    logAction('delete_app_user', "删除用户: {$user['username']}");
                    echo json_encode(['success' => true, 'message' => '删除成功']);
                } else {
                    echo json_encode(['success' => false, 'message' => '删除失败']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '用户不存在']);
            }
            exit;
            
        case 'batch_delete':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $ids = $data['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => '请选择要删除的用户']);
                exit;
            }
            
            $db->beginTransaction();
            
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $db->query("DELETE FROM app_users WHERE id IN ($placeholders)", $ids);
                $db->commit();
                
                logAction('batch_delete_app_users', "批量删除用户: " . implode(',', $ids));
                echo json_encode(['success' => true, 'message' => '批量删除成功']);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => '批量删除失败: ' . $e->getMessage()]);
            }
            exit;
            
        case 'ban_user':
            $id = intval($_GET['id'] ?? 0);
            $user = $db->fetchOne("SELECT * FROM app_users WHERE id = ?", [$id]);
            if ($user) {
                $newStatus = $user['status'] == 1 ? 0 : 1;
                $db->update('app_users', ['status' => $newStatus], 'id = ?', [$id]);
                logAction('ban_app_user', "切换用户状态: {$user['username']}, 新状态: $newStatus");
                echo json_encode(['success' => true, 'message' => '操作成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '用户不存在']);
            }
            exit;
    }
    
    echo json_encode(['success' => false, 'message' => '未知操作']);
    exit;
}

// 获取统计数据（根据管理员权限）
$isSuperAdmin = ($admin['role'] === 'super_admin');

if ($isSuperAdmin) {
    // 超级管理员看所有数据
$stats = [
    'total_projects' => $db->count('projects'),
    'total_cards' => $db->count('cards'),
    'used_cards' => $db->count('cards', 'status = 1'),
        'total_users' => $db->count('app_users'),
        'active_users' => $db->count('app_users', "status = 1 AND last_login > datetime('now', '-7 days', 'localtime')")
    ];
} else {
    // 普通管理员只看自己的数据
    $stats = [
        'total_projects' => $db->count('projects', 'admin_id = ?', [$admin['id']]),
        'total_cards' => $db->count('cards', 'project_id IN (SELECT id FROM projects WHERE admin_id = ?)', [$admin['id']]),
        'used_cards' => $db->count('cards', 'status = 1 AND project_id IN (SELECT id FROM projects WHERE admin_id = ?)', [$admin['id']]),
        'total_users' => $db->count('app_users', 'project_id IN (SELECT id FROM projects WHERE admin_id = ?)', [$admin['id']]),
        'active_users' => $db->count('app_users', "status = 1 AND last_login > datetime('now', '-7 days', 'localtime') AND project_id IN (SELECT id FROM projects WHERE admin_id = ?)", [$admin['id']])
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.0/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 260px;
        }
        
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: #f5f6fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-brand {
            padding: 25px 20px;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-item {
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: block;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        
        .menu-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-left-color: white;
        }
        
        .menu-item i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-card p {
            color: #666;
            margin: 0;
        }
        
        .content-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .content-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .content-card-header h5 {
            margin: 0;
            font-weight: bold;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
        }
        
        .table thead {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 5px 10px;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* DataTables 分页信息固定样式 */
        .content-card {
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .dataTables_wrapper {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .dataTables_wrapper .row:last-child {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 15px 0;
            margin-top: auto;
            border-top: 1px solid #dee2e6;
            z-index: 10;
        }
        
        .dataTables_info {
            padding: 10px 0 !important;
        }
        
        .dataTables_paginate {
            padding: 10px 0 !important;
        }
    </style>
</head>
<body>
    <!-- 侧边栏 -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-shield-lock"></i> <?php echo SITE_NAME; ?>
        </div>
        
        <div class="sidebar-menu">
            <a href="?page=overview" class="menu-item <?php echo $page == 'overview' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> 数据概览
            </a>
            <a href="?page=projects" class="menu-item <?php echo $page == 'projects' ? 'active' : ''; ?>">
                <i class="bi bi-folder"></i> 项目管理
            </a>
            <a href="?page=cards" class="menu-item <?php echo $page == 'cards' ? 'active' : ''; ?>">
                <i class="bi bi-credit-card"></i> 卡密管理
            </a>
            <a href="?page=users" class="menu-item <?php echo $page == 'users' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> 用户管理
            </a>
            <a href="?page=api" class="menu-item <?php echo $page == 'api' ? 'active' : ''; ?>">
                <i class="bi bi-book"></i> API对接
            </a>
            <a href="?page=logs" class="menu-item <?php echo $page == 'logs' ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i> 操作日志
            </a>
            <a href="?page=admins" class="menu-item <?php echo $page == 'admins' ? 'active' : ''; ?>">
                <i class="bi bi-person-gear"></i> 管理员
            </a>
            <a href="?page=settings" class="menu-item <?php echo $page == 'settings' ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i> 系统设置
            </a>
        </div>
    </div>
    
    <!-- 主内容区 -->
    <div class="main-content">
        <!-- 顶部栏 -->
        <div class="top-bar">
            <div>
                <h4 class="mb-0">欢迎回来，<?php echo h($admin['username']); ?>！</h4>
                <small class="text-muted">上次登录：<?php echo $admin['last_login'] ? timeAgo($admin['last_login']) : '首次登录'; ?></small>
            </div>
            <div>
                <div class="user-avatar">
                    <?php echo mb_substr($admin['username'], 0, 1); ?>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-danger ms-2">
                    <i class="bi bi-box-arrow-right"></i> 退出
                </a>
            </div>
        </div>
        
        <!-- 内容区域 -->
        <?php
        switch ($page) {
            case 'overview':
                include 'pages/overview.php';
                break;
            case 'projects':
                include 'pages/projects.php';
                break;
            case 'cards':
                include 'pages/cards.php';
                break;
            case 'users':
                include 'pages/users.php';
                break;
            case 'api':
                include 'pages/api.php';
                break;
            case 'logs':
                include 'pages/logs.php';
                break;
            case 'admins':
                include 'pages/admins.php';
                break;
            case 'settings':
                include 'pages/settings.php';
                break;
            default:
                echo '<div class="alert alert-warning">页面不存在</div>';
        }
        ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.0/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.0/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // 通用删除函数
        function deleteItem(action, id, message = '确定要删除吗？') {
            Swal.fire({
                title: '确认删除',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('', {
                        ajax: 1,
                        action: action,
                        id: id
                    }, function(data) {
                        if (data.success) {
                            Swal.fire('成功', data.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('失败', data.message, 'error');
                        }
                    }, 'json');
                }
            });
        }
        
        // 切换项目状态
        function toggleProjectStatus(id) {
            $.post('', {
                ajax: 1,
                action: 'toggle_project_status',
                id: id
            }, function(data) {
                if (data.success) {
                    location.reload();
                }
            }, 'json');
        }
        
        // 解绑卡密
        function unbindCard(id) {
            Swal.fire({
                title: '确认解绑',
                text: '解绑后卡密将重置为未使用状态',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '确定',
                cancelButtonText: '取消'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('', {
                        ajax: 1,
                        action: 'unbind_card',
                        id: id
                    }, function(data) {
                        if (data.success) {
                            Swal.fire('成功', data.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('失败', data.message, 'error');
                        }
                    }, 'json');
                }
            });
        }
        
        // 复制到剪贴板（全局函数）
        function copyToClipboard(elementId, button) {
            var element = document.getElementById(elementId);
            element.select();
            element.setSelectionRange(0, 99999); // For mobile devices
            
            // 尝试使用新的 Clipboard API
            if (navigator.clipboard) {
                navigator.clipboard.writeText(element.value).then(function() {
                    showCopySuccess(button);
                }).catch(function() {
                    // 降级到 execCommand
                    document.execCommand('copy');
                    showCopySuccess(button);
                });
            } else {
                // 降级到 execCommand
                document.execCommand('copy');
                showCopySuccess(button);
            }
        }
        
        function showCopySuccess(button) {
            var originalHtml = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check-lg"></i> 已复制';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary', 'btn-primary');
            
            setTimeout(function() {
                button.innerHTML = originalHtml;
                button.classList.remove('btn-success');
                if (button.classList.contains('btn-sm')) {
                    button.classList.add('btn-outline-secondary');
                } else {
                    button.classList.add('btn-primary');
                }
            }, 2000);
            
            // 如果存在 SweetAlert2，显示提示
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: '已复制',
                    text: '内容已复制到剪贴板',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        }
        
        // 初始化DataTable
        $(document).ready(function() {
            if ($('.data-table').length > 0) {
                // 先销毁已存在的DataTable实例
                if ($.fn.DataTable.isDataTable('.data-table')) {
                    $('.data-table').DataTable().destroy();
                }
                
                $('.data-table').DataTable({
                    language: {
                        "sProcessing": "处理中...",
                        "sLengthMenu": "显示 _MENU_ 条",
                        "sZeroRecords": "没有匹配结果",
                        "sInfo": "显示第 _START_ 至 _END_ 项，共 _TOTAL_ 项",
                        "sInfoEmpty": "显示第 0 至 0 项，共 0 项",
                        "sInfoFiltered": "(由 _MAX_ 项过滤)",
                        "sInfoPostFix": "",
                        "sSearch": "搜索:",
                        "sUrl": "",
                        "sEmptyTable": "表中数据为空",
                        "sLoadingRecords": "载入中...",
                        "sInfoThousands": ",",
                        "oPaginate": {
                            "sFirst": "首页",
                            "sPrevious": "上页",
                            "sNext": "下页",
                            "sLast": "末页"
                        },
                        "oAria": {
                            "sSortAscending": ": 以升序排列此列",
                            "sSortDescending": ": 以降序排列此列"
                        }
                    },
                    pageLength: 20,
                    order: [[0, 'desc']],
                    destroy: true, // 允许重新初始化
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
                });
            }
        });
    </script>
</body>
</html>


