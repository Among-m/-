<?php
// 用户管理页面
if (!defined('DB_FILE')) exit;

// 搜索参数
$searchKeyword = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$vipFilter = $_GET['vip'] ?? '';

// 构建查询条件
$where = '1=1';
$params = [];

if ($searchKeyword !== '') {
    $where .= ' AND (u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.device_id LIKE ?)';
    $searchTerm = "%{$searchKeyword}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($statusFilter !== '') {
    $where .= ' AND u.status = ?';
    $params[] = intval($statusFilter);
}

if ($vipFilter !== '') {
    $where .= ' AND u.vip_level = ?';
    $params[] = intval($vipFilter);
}

// 获取当前管理员的所有项目ID
$adminProjects = $db->fetchAll("SELECT id FROM projects WHERE admin_id = ?", [$admin['id']]);
$projectIds = array_column($adminProjects, 'id');

// 如果管理员没有项目，显示空列表
if (empty($projectIds)) {
    $users = [];
} else {
    // 构建IN查询
    $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
    $where .= " AND u.project_id IN ($placeholders)";
    $params = array_merge($params, $projectIds);
    
    // 查询app_users表（只显示当前管理员项目的注册用户）
    $users = $db->fetchAll("SELECT u.*, p.name as project_name FROM app_users u 
        LEFT JOIN projects p ON u.project_id = p.id 
        WHERE $where ORDER BY u.id DESC LIMIT 1000", $params);
}

// 为每个用户获取绑定的卡密信息
foreach ($users as $key => $user) {
    // 通过 username 关联查询卡密（device_id 字段存储的是 username）
    $users[$key]['cards'] = $db->fetchAll("SELECT c.*, p.name as project_name FROM cards c 
        LEFT JOIN projects p ON c.project_id = p.id 
        WHERE c.device_id = ? AND c.status = 1 ORDER BY c.activated_at DESC", [$user['username']]);
    $users[$key]['card_count'] = count($users[$key]['cards']);
    $users[$key]['active_cards'] = 0;
    $users[$key]['expired_cards'] = 0;
    $users[$key]['latest_expire_date'] = null; // 最新的有效期
    
    foreach ($users[$key]['cards'] as $card) {
        if ($card['status'] == 1) {
            // 记录最新的有效期
            if (!$users[$key]['latest_expire_date'] || 
                ($card['expire_date'] && strtotime($card['expire_date']) > strtotime($users[$key]['latest_expire_date']))) {
                $users[$key]['latest_expire_date'] = $card['expire_date'];
            }
            
            if ($card['expire_date'] && strtotime($card['expire_date']) > time()) {
                $users[$key]['active_cards']++;
            } elseif (!$card['expire_date']) {
                $users[$key]['active_cards']++;
                $users[$key]['latest_expire_date'] = '永久'; // 永久卡
            } else {
                $users[$key]['expired_cards']++;
            }
        }
    }
}
// 解除任何可能残留的引用
unset($user);

$apiUrl = SITE_URL . '/api.php';
?>


<!-- 顶部统计卡片 - 用户维度 -->
<div class="row g-3 mb-4">
<?php
/* ===== 统计查询（一次性取出，避免多次查询） ===== */
$totalUsers   = (int) $db->fetchOne("SELECT COUNT(*) AS c FROM app_users WHERE 1=1")['c'];
$activeUsers  = (int) $db->fetchOne("SELECT COUNT(*) AS c FROM app_users WHERE status = 1")['c'];
$banUsers     = (int) $db->fetchOne("SELECT COUNT(*) AS c FROM app_users WHERE status = 0")['c'];
$vipUsers     = (int) $db->fetchOne("SELECT COUNT(*) AS c FROM app_users WHERE vip_level > 0")['c'];

$stats = [
    ['label' => '用户总数', 'value' => $totalUsers,  'icon' => 'people',      'color' => 'primary'],
    ['label' => '活跃用户', 'value' => $activeUsers, 'icon' => 'person-check','color' => 'success'],
    ['label' => '禁用用户', 'value' => $banUsers,    'icon' => 'person-x',    'color' => 'danger'],
    ['label' => 'VIP 用户', 'value' => $vipUsers,    'icon' => 'star',        'color' => 'warning'],
];
?>
<?php foreach ($stats as $s): ?>
    <div class="col-6 col-md">
        <div class="d-flex align-items-center p-3 bg-white rounded-3 border border-light h-100
                    transition-all hover-shadow-sm hover-translate-y--1">
            <div class="me-3 flex-shrink-0">
                <div class="d-flex align-items-center justify-content-center rounded-2
                            bg-<?= $s['color'] ?>-subtle text-<?= $s['color'] ?>
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

<!-- 动效 & 配色 -->
<style>
.transition-all        { transition: all .2s ease; }
.hover-shadow-sm:hover { box-shadow: 0 .15rem .35rem rgba(0,0,0,.07) !important; }
.hover-translate-y--1:hover { transform: translateY(-2px); }
.hover-rotate-8:hover  { transform: rotate(8deg); }
</style>

<!-- 用户列表 -->
<div class="content-card">
    <div class="content-card-header">
        <h5><i class="bi bi-people"></i> 注册用户管理</h5>
        <div>
            <button class="btn btn-primary" onclick="showAddUserModal()">
                <i class="bi bi-person-plus"></i> 添加用户
            </button>
            <button class="btn btn-danger ms-2" onclick="batchDeleteUsers()" id="batchDeleteBtn" style="display:none;">
                <i class="bi bi-trash"></i> 批量删除
            </button>
            <span class="badge bg-primary ms-2">总计: <?php echo count($users); ?></span>
        </div>
    </div>
    
    <div class="row mb-3 p-3">
        <div class="col-md-3">
            <input type="text" class="form-control" id="searchInput" placeholder="搜索用户名/邮箱/手机号" 
                value="<?php echo h($searchKeyword); ?>" onkeypress="if(event.key==='Enter') searchUsers()">
        </div>
        <div class="col-md-2">
            <select class="form-select" id="statusFilter" onchange="searchUsers()">
                <option value="">所有状态</option>
                <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>启用</option>
                <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>禁用</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" id="vipFilter" onchange="searchUsers()">
                <option value="">所有等级</option>
                <option value="0" <?php echo $vipFilter === '0' ? 'selected' : ''; ?>>普通用户</option>
                <option value="1" <?php echo $vipFilter === '1' ? 'selected' : ''; ?>>VIP用户</option>
                <option value="2" <?php echo $vipFilter === '2' ? 'selected' : ''; ?>>SVIP用户</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100" onclick="searchUsers()">
                <i class="bi bi-search"></i> 搜索
            </button>
        </div>
        <div class="col-md-2">
            <button class="btn btn-secondary w-100" onclick="resetSearch()">
                <i class="bi bi-arrow-counterclockwise"></i> 重置
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover" style="min-width: 1600px;">
            <thead>
                <tr>
                    <th width="40" style="min-width: 40px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                    </th>
                    <th style="min-width: 50px;">ID</th>
                    <th style="min-width: 100px;">项目</th>
                    <th style="min-width: 100px;">用户名</th>
                    <th style="min-width: 150px;">邮箱</th>
                    <th style="min-width: 110px;">手机号</th>
                    <th style="min-width: 70px;">等级</th>
                    <th style="min-width: 100px;">绑定卡密</th>
                    <th style="min-width: 140px;">卡密有效期</th>
                    <th style="min-width: 100px;">设备型号</th>
                    <th style="min-width: 70px;">状态</th>
                    <th style="min-width: 80px;">登录次数</th>
                    <th style="min-width: 100px;">最后登录</th>
                    <th style="min-width: 100px;">注册时间</th>
                    <th style="min-width: 120px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>" onchange="updateBatchButtons()">
                    </td>
                    <td><?php echo $user['id']; ?></td>
                    <td>
                        <span class="badge bg-info"><?php echo h($user['project_name'] ?? '未分配'); ?></span>
                    </td>
                    <td>
                        <strong><?php echo h($user['username']); ?></strong>
                    </td>
                    <td><?php echo h($user['email'] ?? '-'); ?></td>
                    <td><?php echo h($user['phone'] ?? '-'); ?></td>
                    <td>
                        <?php if ($user['vip_level'] == 0): ?>
                            <span class="badge bg-secondary">普通</span>
                        <?php elseif ($user['vip_level'] == 1): ?>
                            <span class="badge bg-warning">VIP</span>
                        <?php else: ?>
                            <span class="badge bg-danger">SVIP</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['card_count'] > 0): ?>
                            <span class="badge bg-success"><?php echo $user['active_cards']; ?> 有效</span>
                            <?php if ($user['expired_cards'] > 0): ?>
                            <span class="badge bg-secondary"><?php echo $user['expired_cards']; ?> 已过期</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">未绑定</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['latest_expire_date']): ?>
                            <?php if ($user['latest_expire_date'] == '永久'): ?>
                                <span class="badge bg-success">永久有效</span>
                            <?php else: ?>
                                <?php
                                $expireTime = strtotime($user['latest_expire_date']);
                                $now = time();
                                if ($expireTime > $now) {
                                    $days = ceil(($expireTime - $now) / 86400);
                                    echo '<span class="badge bg-success" title="' . h($user['latest_expire_date']) . '">';
                                    echo formatDate($user['latest_expire_date'], 'Y-m-d');
                                    echo '<br><small>剩余 ' . $days . ' 天</small>';
                                    echo '</span>';
                                } else {
                                    echo '<span class="badge bg-danger" title="' . h($user['latest_expire_date']) . '">';
                                    echo formatDate($user['latest_expire_date'], 'Y-m-d');
                                    echo '<br><small>已过期</small>';
                                    echo '</span>';
                                }
                                ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo h($user['device_model'] ?? '-'); ?></td>
                    <td><?php echo getStatusText($user['status']); ?></td>
                    <td><?php echo $user['login_count']; ?></td>
                    <td><?php echo timeAgo($user['last_login']); ?></td>
                    <td><?php echo formatDate($user['created_at'], 'Y-m-d'); ?></td>
                    <td>
                        <button onclick="showUserDetail(<?php echo $user['id']; ?>)" class="btn btn-sm btn-outline-primary" title="查看详情">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button onclick="showEditUserModal(<?php echo $user['id']; ?>)" class="btn btn-sm btn-outline-success" title="编辑">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-outline-danger" title="删除">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                 <?php if (count($users) == 0): ?>
                 <tr>
                     <td colspan="15" class="text-center text-muted py-4">
                         <i class="bi bi-inbox" style="font-size: 48px;"></i>
                         <p class="mt-2">暂无用户数据</p>
                         <?php if (empty($projectIds)): ?>
                         <p class="text-warning">您还没有创建任何项目，请先在项目管理中创建项目</p>
                         <?php endif; ?>
                     </td>
                 </tr>
                 <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 用户详情弹窗 -->
<div class="modal fade" id="userDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-circle"></i> 用户详情</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">加载中...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                <button type="button" class="btn btn-danger" id="banUserBtn" onclick="banUser()">禁用用户</button>
            </div>
        </div>
    </div>
</div>

<!-- 添加/编辑用户弹窗 -->
<div class="modal fade" id="userFormModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userFormTitle">
                    <i class="bi bi-person-plus"></i> 添加用户
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                 <form id="userForm">
                    <input type="hidden" id="userId" name="user_id">
                    <div class="mb-3">
                        <label class="form-label">所属项目 *</label>
                        <select class="form-select" id="projectId" name="project_id" required>
                            <option value="">请选择项目</option>
                            <?php foreach ($adminProjects as $proj): ?>
                            <?php $projInfo = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$proj['id']]); ?>
                            <option value="<?php echo $proj['id']; ?>"><?php echo h($projInfo['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">用户名 *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3" id="passwordGroup">
                        <label class="form-label">密码 *</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="text-muted">编辑时留空表示不修改密码</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">邮箱</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">手机号</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">VIP等级</label>
                        <select class="form-select" id="vipLevel" name="vip_level">
                            <option value="0">普通用户</option>
                            <option value="1">VIP用户</option>
                            <option value="2">SVIP用户</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">余额</label>
                        <input type="number" class="form-control" id="balance" name="balance" value="0" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">状态</label>
                        <select class="form-select" id="status" name="status">
                            <option value="1">启用</option>
                            <option value="0">禁用</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">保存</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentUserId = null;

// 搜索用户
function searchUsers() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const vip = document.getElementById('vipFilter').value;
    
    let url = '?page=users';
    if (search) url += '&search=' + encodeURIComponent(search);
    if (status) url += '&status=' + status;
    if (vip) url += '&vip=' + vip;
    
    location.href = url;
}

// 重置搜索
function resetSearch() {
    location.href = '?page=users';
}

// 全选/取消全选
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateBatchButtons();
}

// 更新批量操作按钮状态
function updateBatchButtons() {
    const checked = document.querySelectorAll('.user-checkbox:checked');
    const batchDeleteBtn = document.getElementById('batchDeleteBtn');
    batchDeleteBtn.style.display = checked.length > 0 ? 'inline-block' : 'none';
}

// 显示添加用户弹窗
function showAddUserModal() {
    document.getElementById('userFormTitle').innerHTML = '<i class="bi bi-person-plus"></i> 添加用户';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('password').required = true;
    new bootstrap.Modal(document.getElementById('userFormModal')).show();
}

// 显示编辑用户弹窗
function showEditUserModal(userId) {
    document.getElementById('userFormTitle').innerHTML = '<i class="bi bi-pencil"></i> 编辑用户';
    document.getElementById('password').required = false;
    
    // 使用AJAX获取用户信息
    fetch('dashboard.php?page=users&action=get_user&id=' + userId)
        .then(res => res.json())
        .then(data => {
             if (data.success) {
                 const user = data.user;
                 document.getElementById('userId').value = user.id;
                 document.getElementById('projectId').value = user.project_id || '';
                 document.getElementById('username').value = user.username;
                 document.getElementById('email').value = user.email || '';
                 document.getElementById('phone').value = user.phone || '';
                 document.getElementById('vipLevel').value = user.vip_level;
                 document.getElementById('balance').value = user.balance;
                 document.getElementById('status').value = user.status;
                 document.getElementById('password').value = '';
                 
                 new bootstrap.Modal(document.getElementById('userFormModal')).show();
             } else {
                alert('获取用户信息失败: ' + data.message);
            }
        })
        .catch(err => {
            alert('请求失败: ' + err.message);
        });
}

// 保存用户
function saveUser() {
    const form = document.getElementById('userForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'save_user');
    
    fetch('dashboard.php?page=users', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('保存成功');
            location.reload();
        } else {
            alert('保存失败: ' + data.message);
        }
    })
    .catch(err => {
        alert('请求失败: ' + err.message);
    });
}

// 显示用户详情
function showUserDetail(userId) {
    currentUserId = userId;
    const modal = new bootstrap.Modal(document.getElementById('userDetailModal'));
    modal.show();
    
    document.getElementById('userDetailContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">加载中...</p>
        </div>
    `;
    
    // 使用AJAX获取用户详情
    fetch('dashboard.php?page=users&action=get_user_detail&id=' + userId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayUserDetail(data.user);
            } else {
                document.getElementById('userDetailContent').innerHTML = `
                    <div class="alert alert-danger">${data.message}</div>
                `;
            }
        })
        .catch(err => {
            document.getElementById('userDetailContent').innerHTML = `
                <div class="alert alert-danger">加载失败: ${err.message}</div>
            `;
        });
}

// 显示用户详情内容
function displayUserDetail(user) {
    let vipBadge = '';
    if (user.vip_level == 0) {
        vipBadge = '<span class="badge bg-secondary">普通用户</span>';
    } else if (user.vip_level == 1) {
        vipBadge = '<span class="badge bg-warning text-dark">VIP用户</span>';
    } else {
        vipBadge = '<span class="badge bg-danger">SVIP用户</span>';
    }
    
    let statusBadge = user.status == 1 
        ? '<span class="badge bg-success">正常</span>' 
        : '<span class="badge bg-danger">禁用</span>';
    
    let cardsHtml = '';
    if (user.cards && user.cards.length > 0) {
        cardsHtml = '<table class="table table-sm mt-2"><thead><tr><th>卡密</th><th>项目</th><th>有效期</th><th>状态</th></tr></thead><tbody>';
        user.cards.forEach(card => {
            let cardStatus = '';
            let expireInfo = '';
            
            if (card.status == 1) {
                if (card.expire_date) {
                    const expireTime = new Date(card.expire_date).getTime();
                    const now = new Date().getTime();
                    if (expireTime > now) {
                        const days = Math.ceil((expireTime - now) / (1000 * 60 * 60 * 24));
                        cardStatus = '<span class="badge bg-success">有效</span>';
                        expireInfo = card.expire_date + ' (剩余' + days + '天)';
                    } else {
                        cardStatus = '<span class="badge bg-danger">已过期</span>';
                        expireInfo = card.expire_date;
                    }
                } else {
                    cardStatus = '<span class="badge bg-success">永久</span>';
                    expireInfo = '永久有效';
                }
            } else if (card.status == 0) {
                cardStatus = '<span class="badge bg-secondary">未激活</span>';
                expireInfo = '-';
            } else {
                cardStatus = '<span class="badge bg-danger">已禁用</span>';
                expireInfo = '-';
            }
            
            cardsHtml += `<tr>
                <td><code>${card.card_key}</code></td>
                <td>${card.project_name || '-'}</td>
                <td>${expireInfo}</td>
                <td>${cardStatus}</td>
            </tr>`;
        });
        cardsHtml += '</tbody></table>';
    } else {
        cardsHtml = '<p class="text-muted">未绑定任何卡密</p>';
    }
    
    const html = `
        <div class="row">
            <div class="col-md-4 text-center">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" 
                     style="width: 120px; height: 120px;">
                    <i class="bi bi-person-circle" style="font-size: 80px; color: #0d6efd;"></i>
                </div>
                <h5 class="mt-3">${user.username}</h5>
                ${vipBadge}
                ${statusBadge}
            </div>
            <div class="col-md-8">
                <table class="table table-borderless">
                    <tr>
                        <td width="120"><strong>用户ID</strong></td>
                        <td>${user.id}</td>
                    </tr>
                    <tr>
                        <td><strong>邮箱</strong></td>
                        <td>${user.email || '-'}</td>
                    </tr>
                    <tr>
                        <td><strong>手机号</strong></td>
                        <td>${user.phone || '-'}</td>
                    </tr>
                    <tr>
                        <td><strong>注册时间</strong></td>
                        <td>${user.created_at}</td>
                    </tr>
                    <tr>
                        <td><strong>最后登录</strong></td>
                        <td>${user.last_login || '-'}</td>
                    </tr>
                    <tr>
                        <td><strong>登录次数</strong></td>
                        <td>${user.login_count}</td>
                    </tr>
                    <tr>
                        <td><strong>设备ID</strong></td>
                        <td><small><code>${user.device_id || '-'}</code></small></td>
                    </tr>
                    <tr>
                        <td><strong>设备型号</strong></td>
                        <td>${user.device_model || '-'}</td>
                    </tr>
                    <tr>
                        <td><strong>余额</strong></td>
                        <td>¥${user.balance}</td>
                    </tr>
                </table>
            </div>
        </div>
        <hr>
        <h6><i class="bi bi-credit-card"></i> 绑定的卡密 (${user.card_count})</h6>
        ${cardsHtml}
    `;
    
    document.getElementById('userDetailContent').innerHTML = html;
    
    // 更新底部按钮状态
    const banBtn = document.getElementById('banUserBtn');
    if (user.status == 1) {
        banBtn.className = 'btn btn-warning';
        banBtn.innerHTML = '<i class="bi bi-ban"></i> 禁用用户';
        banBtn.setAttribute('data-action', 'ban');
    } else {
        banBtn.className = 'btn btn-success';
        banBtn.innerHTML = '<i class="bi bi-check-circle"></i> 启用用户';
        banBtn.setAttribute('data-action', 'enable');
    }
}

// 删除用户
function deleteUser(userId) {
    if (!confirm('确定要删除该用户吗？此操作不可恢复！')) {
        return;
    }
    
    fetch('dashboard.php?page=users&action=delete_user&id=' + userId, {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('删除成功');
            location.reload();
        } else {
            alert('删除失败: ' + data.message);
        }
    })
    .catch(err => {
        alert('请求失败: ' + err.message);
    });
}

// 批量删除
function batchDeleteUsers() {
    const checked = document.querySelectorAll('.user-checkbox:checked');
    const ids = Array.from(checked).map(cb => cb.value);
    
    if (ids.length === 0) {
        alert('请先选择要删除的用户');
        return;
    }
    
    if (!confirm(`确定要删除选中的 ${ids.length} 个用户吗？此操作不可恢复！`)) {
        return;
    }
    
    fetch('dashboard.php?page=users&action=batch_delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ids: ids})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('批量删除成功');
            location.reload();
        } else {
            alert('批量删除失败: ' + data.message);
        }
    })
    .catch(err => {
        alert('请求失败: ' + err.message);
    });
}

// 禁用用户
function banUser() {
    if (!currentUserId) return;
    
    const banBtn = document.getElementById('banUserBtn');
    const action = banBtn.getAttribute('data-action');
    const confirmMsg = action === 'ban' ? '确定要禁用该用户吗？' : '确定要启用该用户吗？';
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    fetch('dashboard.php?page=users&action=ban_user&id=' + currentUserId, {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('操作成功');
            location.reload();
        } else {
            alert('操作失败: ' + data.message);
        }
    })
    .catch(err => {
        alert('请求失败: ' + err.message);
    });
}
</script>

