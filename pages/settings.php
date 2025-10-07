<?php
// 系统设置页面
if (!defined('DB_FILE')) exit;

$error = '';
$success = '';

// 处理修改密码
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($oldPassword) || empty($newPassword)) {
        $error = '请填写完整信息';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '两次密码不一致';
    } elseif (strlen($newPassword) < 6) {
        $error = '密码长度不能少于6位';
    } else {
        $currentAdmin = getCurrentAdmin();
        if (password_verify($oldPassword, $currentAdmin['password'])) {
            $db->update('admins', [
                'password' => password_hash($newPassword, PASSWORD_BCRYPT),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$currentAdmin['id']]);
            
            logAction('change_password', '修改密码');
            $success = '密码修改成功';
        } else {
            $error = '原密码错误';
        }
    }
}

// 处理更新个人信息
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? '');
    
    $db->update('admins', [
        'email' => $email,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$admin['id']]);
    
    logAction('update_profile', '更新个人信息');
    $success = '信息更新成功';
    $admin = getCurrentAdmin();
}

// 获取系统信息
$dbSize = file_exists(DB_FILE) ? filesize(DB_FILE) : 0;
$dbSizeFormatted = $dbSize > 1024 * 1024 ? round($dbSize / 1024 / 1024, 2) . ' MB' : round($dbSize / 1024, 2) . ' KB';
?>

<div class="row">
    <div class="col-md-8">
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo h($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo h($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- 个人信息 -->
        <div class="content-card mb-4">
            <div class="content-card-header">
                <h5><i class="bi bi-person"></i> 个人信息</h5>
            </div>
            
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">用户名</label>
                        <input type="text" class="form-control" value="<?php echo h($admin['username']); ?>" disabled>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">邮箱</label>
                        <input type="email" class="form-control" name="email" value="<?php echo h($admin['email']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">角色</label>
                        <input type="text" class="form-control" value="<?php echo $admin['role'] == 'super_admin' ? '超级管理员' : '管理员'; ?>" disabled>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">最后登录</label>
                        <input type="text" class="form-control" value="<?php echo $admin['last_login'] ? formatDate($admin['last_login']) : '首次登录'; ?>" disabled>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> 保存
                    </button>
                </div>
            </form>
        </div>
        
        <!-- 修改密码 -->
        <div class="content-card">
            <div class="content-card-header">
                <h5><i class="bi bi-lock"></i> 修改密码</h5>
            </div>
            
            <form method="POST">
                <input type="hidden" name="change_password" value="1">
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">原密码</label>
                        <input type="password" class="form-control" name="old_password" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">新密码</label>
                        <input type="password" class="form-control" name="new_password" required>
                        <small class="text-muted">至少6位</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">确认新密码</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i> 修改密码
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- 系统信息 -->
        <div class="content-card mb-4">
            <div class="content-card-header">
                <h5><i class="bi bi-info-circle"></i> 系统信息</h5>
            </div>
            
            <table class="table table-sm">
                <tr>
                    <td>系统版本</td>
                    <td><strong>v1.0.0</strong></td>
                </tr>
                <tr>
                    <td>PHP版本</td>
                    <td><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                    <td>数据库大小</td>
                    <td><?php echo $dbSizeFormatted; ?></td>
                </tr>
                <tr>
                    <td>服务器时间</td>
                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- 统计信息 -->
        <div class="content-card">
            <div class="content-card-header">
                <h5><i class="bi bi-bar-chart"></i> 数据统计</h5>
            </div>
            
            <table class="table table-sm">
                <tr>
                    <td>项目总数</td>
                    <td><strong><?php echo $stats['total_projects']; ?></strong></td>
                </tr>
                <tr>
                    <td>卡密总数</td>
                    <td><strong><?php echo $stats['total_cards']; ?></strong></td>
                </tr>
                <tr>
                    <td>已使用卡密</td>
                    <td><strong><?php echo $stats['used_cards']; ?></strong></td>
                </tr>
                <tr>
                    <td>用户总数</td>
                    <td><strong><?php echo $stats['total_users']; ?></strong></td>
                </tr>
                <tr>
                    <td>活跃用户</td>
                    <td><strong><?php echo $stats['active_users']; ?></strong></td>
                </tr>
            </table>
        </div>
    </div>
</div>



