<?php
// 管理员管理页面
if (!defined('DB_FILE')) exit;

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax'])) {
    $formAction = $_POST['form_action'] ?? '';
    
    if ($formAction == 'add' || $formAction == 'edit') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'admin';
        $status = intval($_POST['status'] ?? 1);
        
        if (empty($username)) {
            $error = '用户名不能为空';
        } elseif ($formAction == 'add' && empty($password)) {
            $error = '密码不能为空';
        } else {
            if ($formAction == 'add') {
                // 只有超级管理员可以添加新管理员
                if ($admin['role'] !== 'super_admin') {
                    $error = '无权添加管理员';
                } else {
                    // 检查用户名是否存在
                    $exists = $db->fetchOne("SELECT id FROM admins WHERE username = ?", [$username]);
                    if ($exists) {
                        $error = '用户名已存在';
                    } else {
                        $data = [
                            'username' => $username,
                            'password' => password_hash($password, PASSWORD_BCRYPT),
                            'email' => $email,
                            'role' => $role,
                            'status' => $status
                        ];
                        
                        if ($db->insert('admins', $data)) {
                            logAction('create_admin', "创建管理员: $username");
                            $success = '管理员创建成功';
                            $action = 'list';
                        } else {
                            $error = '创建失败';
                        }
                    }
                }
            } else {
                $id = intval($_POST['id'] ?? 0);
                
                // 权限验证：普通管理员只能编辑自己
                if ($admin['role'] !== 'super_admin' && $id != $admin['id']) {
                    $error = '无权编辑其他管理员';
                } else {
                    $data = [
                        'username' => $username,
                        'email' => $email,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // 只有超级管理员可以修改角色和状态
                    if ($admin['role'] === 'super_admin') {
                        $data['role'] = $role;
                        $data['status'] = $status;
                    }
                    
                    // 如果提供了新密码则更新
                    if (!empty($password)) {
                        $data['password'] = password_hash($password, PASSWORD_BCRYPT);
                    }
                    
                    if (!$error && $db->update('admins', $data, 'id = ?', [$id])) {
                        logAction('update_admin', "更新管理员: $username");
                        $success = '管理员更新成功';
                        $action = 'list';
                    } else if (!$error) {
                        $error = '更新失败';
                    }
                }
            }
        }
    } elseif ($formAction == 'change_password') {
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
                    'password' => password_hash($newPassword, PASSWORD_BCRYPT)
                ], 'id = ?', [$currentAdmin['id']]);
                
                logAction('change_password', '修改密码');
                $success = '密码修改成功';
            } else {
                $error = '原密码错误';
            }
        }
    }
}

// 列表视图
if ($action == 'list') {
    // 检查是否为超级管理员
    $isSuperAdmin = ($admin['role'] === 'super_admin');
    
    // 根据权限获取管理员列表
    if ($isSuperAdmin) {
        // 超级管理员看所有管理员
        $admins = $db->fetchAll("SELECT * FROM admins ORDER BY id ASC");
    } else {
        // 普通管理员只看自己
        $admins = $db->fetchAll("SELECT * FROM admins WHERE id = ? ORDER BY id ASC", [$admin['id']]);
    }
    ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo h($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="content-card">
        <div class="content-card-header">
            <h5><i class="bi bi-person-gear"></i> <?php echo $isSuperAdmin ? '管理员列表' : '我的信息'; ?></h5>
            <?php if ($isSuperAdmin): ?>
            <a href="?page=admins&action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> 添加管理员
            </a>
            <?php endif; ?>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>邮箱</th>
                        <th>角色</th>
                        <th>状态</th>
                        <th>最后登录</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $adminUser): ?>
                    <tr>
                        <td><?php echo $adminUser['id']; ?></td>
                        <td>
                            <strong><?php echo h($adminUser['username']); ?></strong>
                            <?php if ($adminUser['id'] == $admin['id']): ?>
                            <span class="badge bg-primary">当前</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($adminUser['email']); ?></td>
                        <td>
                            <?php if ($adminUser['role'] == 'super_admin'): ?>
                            <span class="badge bg-danger">超级管理员</span>
                            <?php else: ?>
                            <span class="badge bg-info">管理员</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo getStatusText($adminUser['status']); ?></td>
                        <td><?php echo $adminUser['last_login'] ? timeAgo($adminUser['last_login']) : '-'; ?></td>
                        <td><?php echo formatDate($adminUser['created_at'], 'Y-m-d'); ?></td>
                        <td>
                            <?php if ($isSuperAdmin || $adminUser['id'] == $admin['id']): ?>
                            <a href="?page=admins&action=edit&id=<?php echo $adminUser['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> 编辑
                            </a>
                            <?php endif; ?>
                            <?php if ($isSuperAdmin && $adminUser['id'] != $admin['id']): ?>
                            <button onclick="deleteItem('delete_admin', <?php echo $adminUser['id']; ?>)" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
<?php } elseif ($action == 'add' || $action == 'edit') {
    // 权限检查
    $isSuperAdmin = ($admin['role'] === 'super_admin');
    
    // 添加操作只允许超级管理员
    if ($action == 'add' && !$isSuperAdmin) {
        echo '<div class="alert alert-danger">无权添加管理员</div>';
        return;
    }
    
    $adminUser = null;
    if ($action == 'edit') {
        $id = intval($_GET['id'] ?? 0);
        
        // 普通管理员只能编辑自己
        if (!$isSuperAdmin && $id != $admin['id']) {
            echo '<div class="alert alert-danger">无权编辑其他管理员</div>';
            return;
        }
        
        $adminUser = $db->fetchOne("SELECT * FROM admins WHERE id = ?", [$id]);
        if (!$adminUser) {
            echo '<div class="alert alert-danger">管理员不存在</div>';
            return;
        }
    }
    ?>
    
    <div class="content-card">
        <div class="content-card-header">
            <h5><i class="bi bi-person-plus"></i> <?php echo $action == 'add' ? '添加管理员' : '编辑管理员'; ?></h5>
            <a href="?page=admins" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> 返回
            </a>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo h($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="form_action" value="<?php echo $action; ?>">
            <?php if ($adminUser): ?>
            <input type="hidden" name="id" value="<?php echo $adminUser['id']; ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">用户名 *</label>
                    <input type="text" class="form-control" name="username" value="<?php echo h($adminUser['username'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">邮箱</label>
                    <input type="email" class="form-control" name="email" value="<?php echo h($adminUser['email'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">密码 <?php echo $action == 'edit' ? '（留空则不修改）' : '*'; ?></label>
                    <input type="password" class="form-control" name="password" <?php echo $action == 'add' ? 'required' : ''; ?>>
                </div>
                
                <?php if ($isSuperAdmin): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">角色</label>
                    <select class="form-select" name="role">
                        <option value="admin" <?php echo (!$adminUser || $adminUser['role'] == 'admin') ? 'selected' : ''; ?>>管理员</option>
                        <option value="super_admin" <?php echo ($adminUser && $adminUser['role'] == 'super_admin') ? 'selected' : ''; ?>>超级管理员</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">状态</label>
                    <select class="form-select" name="status">
                        <option value="1" <?php echo (!$adminUser || $adminUser['status'] == 1) ? 'selected' : ''; ?>>启用</option>
                        <option value="0" <?php echo ($adminUser && $adminUser['status'] == 0) ? 'selected' : ''; ?>>禁用</option>
                    </select>
                </div>
                <?php else: ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">角色</label>
                    <input type="text" class="form-control" value="<?php echo $adminUser['role'] == 'super_admin' ? '超级管理员' : '管理员'; ?>" readonly disabled>
                    <small class="text-muted">角色由超级管理员设置</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">状态</label>
                    <input type="text" class="form-control" value="<?php echo $adminUser['status'] == 1 ? '启用' : '禁用'; ?>" readonly disabled>
                    <small class="text-muted">状态由超级管理员设置</small>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="text-end">
                <a href="?page=admins" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> 保存
                </button>
            </div>
        </form>
    </div>
    
<?php } ?>



