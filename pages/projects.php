<?php
// 项目管理页面
if (!defined('DB_FILE')) exit;

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax'])) {
    $formAction = $_POST['form_action'] ?? '';
    
    if ($formAction == 'add' || $formAction == 'edit') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = intval($_POST['status'] ?? 1);
        
        if (empty($name)) {
            $error = '项目名称不能为空';
        } else {
            $data = [
                'name' => $name,
                'description' => $description,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($formAction == 'add') {
                // 添加管理员ID
                $data['admin_id'] = $admin['id'];
                
                $projectId = $db->insert('projects', $data);
                if ($projectId) {
                    // 自动生成API Key
                    $apiToken = generateApiToken($projectId, $name . ' - 默认令牌', null); // 永不过期
                    
                    logAction('create_project', "创建项目: $name");
                    $success = '项目创建成功，已自动生成API Key';
                    $_SESSION['new_project_token'] = $apiToken; // 保存令牌供显示
                    $action = 'list';
                } else {
                    $error = '项目创建失败';
                }
            } else {
                $id = intval($_POST['id'] ?? 0);
                
                // 验证项目归属（非超级管理员）
                if ($admin['role'] !== 'super_admin') {
                    $existProject = $db->fetchOne("SELECT * FROM projects WHERE id = ? AND admin_id = ?", [$id, $admin['id']]);
                    if (!$existProject) {
                        $error = '无权编辑此项目';
                    }
                }
                
                if (!$error && $db->update('projects', $data, 'id = ?', [$id])) {
                    logAction('update_project', "更新项目: $name", $id);
                    $success = '项目更新成功';
                    $action = 'list';
                } else if (!$error) {
                    $error = '项目更新失败';
                }
            }
        }
    }
}

// 列表视图
if ($action == 'list') {
    // 检查是否为超级管理员
    $isSuperAdmin = ($admin['role'] === 'super_admin');
    
    if ($isSuperAdmin) {
        // 超级管理员看所有项目
        $projects = $db->fetchAll("SELECT p.*, a.username as admin_name,
            (SELECT COUNT(*) FROM cards WHERE project_id = p.id) as total_cards,
            (SELECT COUNT(*) FROM cards WHERE project_id = p.id AND status = 1) as used_cards,
            (SELECT COUNT(*) FROM app_users WHERE project_id = p.id) as total_users,
            (SELECT token FROM api_tokens WHERE project_id = p.id ORDER BY created_at DESC LIMIT 1) as api_token
            FROM projects p 
            LEFT JOIN admins a ON p.admin_id = a.id
            ORDER BY p.id DESC");
    } else {
        // 普通管理员只看自己的项目
        $projects = $db->fetchAll("SELECT p.*, a.username as admin_name,
            (SELECT COUNT(*) FROM cards WHERE project_id = p.id) as total_cards,
            (SELECT COUNT(*) FROM cards WHERE project_id = p.id AND status = 1) as used_cards,
            (SELECT COUNT(*) FROM app_users WHERE project_id = p.id) as total_users,
            (SELECT token FROM api_tokens WHERE project_id = p.id ORDER BY created_at DESC LIMIT 1) as api_token
            FROM projects p 
            LEFT JOIN admins a ON p.admin_id = a.id
            WHERE p.admin_id = ? 
            ORDER BY p.id DESC", [$admin['id']]);
    }
    
    // 计算统计数据
    $totalProjects = count($projects);
    $activeProjects = 0;
    $inactiveProjects = 0;
    $totalCards = 0;
    $totalUsers = 0;
    
    foreach ($projects as $project) {
        if ($project['status'] == 1) {
            $activeProjects++;
        } else {
            $inactiveProjects++;
        }
        $totalCards += $project['total_cards'];
        $totalUsers += $project['total_users'];
    }
    ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo h($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['new_project_token'])): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <h6><i class="bi bi-key-fill"></i> 自动生成的API Key（请妥善保存）：</h6>
        <div class="input-group mt-2">
            <input type="text" class="form-control" id="newProjectToken" value="<?php echo h($_SESSION['new_project_token']); ?>" readonly>
            <button class="btn btn-primary" onclick="copyToClipboard('newProjectToken', this)">
                <i class="bi bi-clipboard"></i> 复制
            </button>
        </div>
        <small class="text-muted mt-2 d-block">此令牌永不过期，可在"API管理"页面中查看和管理</small>
    </div>
    <?php unset($_SESSION['new_project_token']); ?>
    <?php endif; ?>
    <!-- 统计卡片 - 极简现代 + 悬停微动效 -->
<div class="row g-3 mb-4">
<?php
$stats = [
    ['label' => '项目总数',  'value' => $totalProjects,  'icon' => 'folder',      'color' => 'indigo'],
    ['label' => '启用项目',  'value' => $activeProjects, 'icon' => 'check-circle', 'color' => 'green'],
    ['label' => '禁用项目',  'value' => $inactiveProjects,'icon' => 'slash-circle', 'color' => 'orange'],
    ['label' => '总卡密数',  'value' => $totalCards,     'icon' => 'key',         'color' => 'teal'],
    ['label' => '总用户数',  'value' => $totalUsers,     'icon' => 'people',      'color' => 'slate'],
];
foreach ($stats as $s): ?>
    <div class="col-6 col-md">
        <div class="d-flex align-items-center p-3 bg-white rounded-3 border border-light h-100
                    transition-all hover-shadow-sm hover-translate-y--1">
            <div class="me-3 flex-shrink-0">
                <div class="d-flex align-items-center justify-content-center rounded-2
                            bg-<?= $s['color'] ?>-bg-opacity-10 text-<?= $s['color'] ?>
                            transition-transform hover-rotate-8"
                     style="width: 48px; height: 48px;">
                    <i class="bi bi-<?= $s['icon'] ?> fs-5"></i>
                </div>
            </div>
            <div>
                <div class="text-muted small"><?= $s['label'] ?></div>
                <div class="fs-5 fw-semibold text-<?= $s['color'] ?>">
                    <?= number_format($s['value']) ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- 自定义配色 -->
<style>
.text-indigo   { color: #6366f1 !important; }
.bg-indigo-bg-opacity-10 { background-color: rgba(99, 102, 241, 0.1) !important; }

.text-green    { color: #10b981 !important; }
.bg-green-bg-opacity-10  { background-color: rgba(16, 185, 129, 0.1) !important; }

.text-orange   { color: #f53f3f !important; }
.bg-orange-bg-opacity-10 { background-color: rgba(216, 88, 88, 0.1) !important; }

.text-teal     { color: #165dff !important; }
.bg-teal-bg-opacity-10   { background-color: rgba(71, 107, 218, 0.1) !important; }

.text-slate    { color: #f59e0b !important; }
.bg-slate-bg-opacity-10  { background-color: rgba(245, 158, 11, 0.1) !important; }

/* 动效 */
.transition-all        { transition: all .2s ease; }
.hover-shadow-sm:hover { box-shadow: 0 .15rem .35rem rgba(0,0,0,.07) !important; }
.hover-translate-y--1:hover { transform: translateY(-2px); }
.hover-rotate-8:hover  { transform: rotate(8deg); }
</style>

    <div class="content-card" style="min-height: 500px;">
        <div class="content-card-header">
            <h5><i class="bi bi-folder"></i> 项目列表</h5>
            <a href="?page=projects&action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> 创建项目
            </a>
        </div>
        
        <table class="table table-hover data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if ($isSuperAdmin): ?>
                    <th>管理员</th>
                    <?php endif; ?>
                    <th>项目名称</th>
                    <th>API Key</th>
                    <th>卡密数</th>
                    <th>已使用</th>
                    <th>用户数</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td><?php echo $project['id']; ?></td>
                    <?php if ($isSuperAdmin): ?>
                    <td><span class="badge bg-primary"><?php echo h($project['admin_name'] ?? '未知'); ?></span></td>
                    <?php endif; ?>
                    <td>
                        <strong><?php echo h($project['name']); ?></strong>
                        <?php if ($project['description']): ?>
                        <br><small class="text-muted"><?php echo h($project['description']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($project['api_token']): ?>
                        <div class="input-group input-group-sm" style="max-width: 250px;">
                            <input type="text" class="form-control form-control-sm" id="token_list_<?php echo $project['id']; ?>" value="<?php echo h($project['api_token']); ?>" readonly style="font-family: monospace; font-size: 10px;">
                            <button class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard('token_list_<?php echo $project['id']; ?>', this)" title="复制令牌">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <span class="text-muted">未生成</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($project['total_cards']); ?></td>
                    <td><?php echo number_format($project['used_cards']); ?></td>
                    <td><?php echo number_format($project['total_users']); ?></td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" <?php echo $project['status'] == 1 ? 'checked' : ''; ?> 
                                onclick="toggleProjectStatus(<?php echo $project['id']; ?>)">
                        </div>
                    </td>
                    <td><?php echo formatDate($project['created_at'], 'Y-m-d'); ?></td>
                    <td>
                        <a href="?page=projects&action=edit&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-primary" title="编辑">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="?page=cards&project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-info" title="卡密管理">
                            <i class="bi bi-credit-card"></i>
                        </a>
                        <a href="?page=api&project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-success" title="API Key">
                            <i class="bi bi-key"></i>
                        </a>
                        <button onclick="deleteItem('delete_project', <?php echo $project['id']; ?>, '删除项目将同时删除所有相关数据，确定吗？')" class="btn btn-sm btn-outline-danger" title="删除">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
<?php } elseif ($action == 'add' || $action == 'edit') {
    $project = null;
    $projectToken = null;
    if ($action == 'edit') {
        $id = intval($_GET['id'] ?? 0);
        $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$id]);
        if (!$project) {
            echo '<div class="alert alert-danger">项目不存在</div>';
            return;
        }
        // 获取项目的API Key
        $projectToken = $db->fetchOne("SELECT * FROM api_tokens WHERE project_id = ? ORDER BY created_at DESC LIMIT 1", [$id]);
    }
    ?>
    
    <div class="content-card">
        <div class="content-card-header">
            <h5><i class="bi bi-folder-plus"></i> <?php echo $action == 'add' ? '创建项目' : '编辑项目'; ?></h5>
            <a href="?page=projects" class="btn btn-outline-secondary">
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
            <?php if ($project): ?>
            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">项目名称 *</label>
                    <input type="text" class="form-control" name="name" value="<?php echo h($project['name'] ?? ''); ?>" required>
                </div>
                
                <?php if ($action == 'edit' && $projectToken): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">API Key 
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="resetProjectToken(<?php echo $project['id']; ?>)" title="重置令牌">
                            <i class="bi bi-arrow-clockwise"></i> 重置
                        </button>
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="projectApiToken" value="<?php echo h($projectToken['token']); ?>" readonly style="font-family: monospace; font-size: 12px;">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('projectApiToken', this)">
                            <i class="bi bi-clipboard"></i> 复制
                        </button>
                    </div>
                    <small class="text-muted">
                        <?php if ($projectToken['expire_at']): ?>
                            过期时间：<?php echo formatDate($projectToken['expire_at'], 'Y-m-d H:i'); ?>
                        <?php else: ?>
                            <span class="text-success">永不过期</span>
                        <?php endif; ?>
                        | <a href="?page=api&project_id=<?php echo $project['id']; ?>">管理所有令牌</a>
                    </small>
                </div>
                <?php elseif ($action == 'add'): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">API Key</label>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> 保存后将自动生成API Key
                    </div>
                </div>
                <?php elseif ($action == 'edit' && !$projectToken): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">API Key</label>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle"></i> 该项目还没有API Key
                        <button type="button" class="btn btn-sm btn-primary ms-2" onclick="generateProjectToken(<?php echo $project['id']; ?>)">
                            <i class="bi bi-plus-circle"></i> 生成令牌
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-12 mb-3">
                    <label class="form-label">项目描述</label>
                    <textarea class="form-control" name="description" rows="3"><?php echo h($project['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">状态</label>
                    <select class="form-select" name="status">
                        <option value="1" <?php echo (!$project || $project['status'] == 1) ? 'selected' : ''; ?>>启用</option>
                        <option value="0" <?php echo ($project && $project['status'] == 0) ? 'selected' : ''; ?>>禁用</option>
                    </select>
                </div>
            </div>
            
            <div class="text-end">
                <a href="?page=projects" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> 保存
                </button>
            </div>
        </form>
    </div>
    
<?php } ?>

<script>
// 重置项目令牌
function resetProjectToken(projectId) {
    Swal.fire({
        title: '确认重置令牌？',
        html: '<p>重置后旧令牌将<strong class="text-danger">立即失效</strong>，所有使用旧令牌的应用将无法访问！</p><p>此操作不可撤销，确定要继续吗？</p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '确定重置',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed) {
            // 显示加载中
            Swal.fire({
                title: '正在重置令牌...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // 发送AJAX请求
            $.post('', {
                ajax: 1,
                action: 'reset_project_token',
                project_id: projectId
            }, function(data) {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '重置成功！',
                        html: '<p>新令牌已生成，请立即复制保存：</p>' +
                              '<div class="input-group mt-2">' +
                              '<input type="text" class="form-control" id="newResetToken" value="' + data.token + '" readonly style="font-family: monospace;">' +
                              '<button class="btn btn-primary" onclick="copyToClipboard(\'newResetToken\', this)"><i class="bi bi-clipboard"></i> 复制</button>' +
                              '</div>',
                        showConfirmButton: true,
                        confirmButtonText: '我已保存',
                        allowOutsideClick: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('失败', data.message || '令牌重置失败', 'error');
                }
            }, 'json').fail(function() {
                Swal.fire('错误', '请求失败，请稍后重试', 'error');
            });
        }
    });
}

// 生成项目令牌（针对没有令牌的项目）
function generateProjectToken(projectId) {
    Swal.fire({
        title: '生成API Key',
        text: '确定要为该项目生成API Key吗？',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '确定',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '正在生成令牌...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.post('', {
                ajax: 1,
                action: 'generate_project_token',
                project_id: projectId
            }, function(data) {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '生成成功！',
                        html: '<p>新令牌已生成，请立即复制保存：</p>' +
                              '<div class="input-group mt-2">' +
                              '<input type="text" class="form-control" id="newGenToken" value="' + data.token + '" readonly style="font-family: monospace;">' +
                              '<button class="btn btn-primary" onclick="copyToClipboard(\'newGenToken\', this)"><i class="bi bi-clipboard"></i> 复制</button>' +
                              '</div>',
                        showConfirmButton: true,
                        confirmButtonText: '我已保存'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('失败', data.message || '令牌生成失败', 'error');
                }
            }, 'json').fail(function() {
                Swal.fire('错误', '请求失败，请稍后重试', 'error');
            });
        }
    });
}
</script>