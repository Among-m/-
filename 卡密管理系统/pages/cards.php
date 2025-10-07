<?php
// 卡密管理页面
if (!defined('DB_FILE')) exit;

$action = $_GET['action'] ?? 'list';
$projectId = intval($_GET['project_id'] ?? 0);
$error = '';
$success = '';

// 处理批量生成卡密
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_cards'])) {
    $projectId = intval($_POST['project_id'] ?? 0);
    $count = intval($_POST['count'] ?? 0);
    $cardType = $_POST['card_type'] ?? 'time';
    $duration = intval($_POST['duration'] ?? 0);
    $maxUseCount = intval($_POST['max_use_count'] ?? 1);
    
    if ($projectId <= 0 || $count <= 0) {
        $error = '参数错误';
    } elseif ($count > 1000) {
        $error = '单次最多生成1000个卡密';
    } else {
        $cards = generateBatchCards($projectId, $count, $cardType, $duration, $maxUseCount);
        if ($cards) {
            logAction('generate_cards', "生成 $count 个卡密", $projectId);
            $success = "成功生成 $count 个卡密";
            $_SESSION['generated_cards'] = $cards;
            header('Location: ?page=cards&action=view_generated');
            exit;
        } else {
            $error = '生成卡密失败';
        }
    }
}

// 导出已移至 dashboard.php 处理（在HTML输出之前）

// 列表视图
if ($action == 'list') {
    // 检查是否为超级管理员
    $isSuperAdmin = ($admin['role'] === 'super_admin');
    
    $where = '1=1';
    $params = [];
    
    // 非超级管理员只能看自己项目的卡密
    if (!$isSuperAdmin) {
        $where .= ' AND p.admin_id = ?';
        $params[] = $admin['id'];
    }
    
    if ($projectId > 0) {
        $where .= ' AND c.project_id = ?';
        $params[] = $projectId;
    }
    
    $statusFilter = $_GET['status'] ?? '';
    if ($statusFilter !== '') {
        $where .= ' AND c.status = ?';
        $params[] = intval($statusFilter);
    }
    
    $searchKey = $_GET['search'] ?? '';
    if ($searchKey) {
        $where .= ' AND c.card_key LIKE ?';
        $params[] = '%' . $searchKey . '%';
    }
    
    $cards = $db->fetchAll("SELECT c.*, p.name as project_name FROM cards c LEFT JOIN projects p ON c.project_id = p.id WHERE $where ORDER BY c.id DESC LIMIT 1000", $params);
    
    // 获取项目列表（根据权限）
    if ($isSuperAdmin) {
        $projects = $db->fetchAll("SELECT * FROM projects ORDER BY name");
    } else {
        $projects = $db->fetchAll("SELECT * FROM projects WHERE admin_id = ? ORDER BY name", [$admin['id']]);
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
            <h5><i class="bi bi-credit-card"></i> 卡密列表</h5>
            <div>
                <a href="?page=cards&action=generate" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> 生成卡密
                </a>
                <div class="btn-group">
                    <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> 导出
                    </button>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">选择导出格式</h6></li>
                        <li><a class="dropdown-item" href="?page=cards&action=export&format=csv<?php echo $projectId ? '&project_id=' . $projectId : ''; ?><?php echo $statusFilter !== '' ? '&status=' . $statusFilter : ''; ?>">
                            <i class="bi bi-filetype-csv"></i> CSV格式（Excel）
                        </a></li>
                        <li><a class="dropdown-item" href="?page=cards&action=export&format=txt_simple<?php echo $projectId ? '&project_id=' . $projectId : ''; ?><?php echo $statusFilter !== '' ? '&status=' . $statusFilter : ''; ?>">
                            <i class="bi bi-filetype-txt"></i> TXT格式（仅卡密）
                        </a></li>
                        <li><a class="dropdown-item" href="?page=cards&action=export&format=txt_detailed<?php echo $projectId ? '&project_id=' . $projectId : ''; ?><?php echo $statusFilter !== '' ? '&status=' . $statusFilter : ''; ?>">
                            <i class="bi bi-file-text"></i> TXT格式（详细信息）
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- 筛选 -->
        <div class="row mb-3">
            <div class="col-md-3">
                <select class="form-select" onchange="location.href='?page=cards&project_id=' + this.value + '<?php echo $statusFilter !== '' ? '&status=' . $statusFilter : ''; ?>'">
                    <option value="0">所有项目</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $projectId == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo h($p['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" onchange="location.href='?page=cards<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>&status=' + this.value">
                    <option value="">所有状态</option>
                    <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>未使用</option>
                    <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>已激活</option>
                    <option value="2" <?php echo $statusFilter === '2' ? 'selected' : ''; ?>>已禁用</option>
                </select>
            </div>
            <div class="col-md-6">
                <form method="GET" class="d-flex">
                    <input type="hidden" name="page" value="cards">
                    <?php if ($projectId): ?>
                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                    <?php endif; ?>
                    <input type="text" class="form-control me-2" name="search" placeholder="搜索卡密..." value="<?php echo h($searchKey); ?>">
                    <button type="submit" class="btn btn-primary">搜索</button>
                </form>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>卡密</th>
                        <th>项目</th>
                        <th>类型</th>
                        <th>时长</th>
                        <th>状态</th>
                        <th>设备ID</th>
                        <th>创建时间</th>
                        <th>激活时间</th>
                        <th>过期时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cards as $card): ?>
                    <tr>
                        <td><?php echo $card['id']; ?></td>
                        <td><code><?php echo h($card['card_key']); ?></code></td>
                        <td><?php echo h($card['project_name']); ?></td>
                        <td><?php echo getCardTypeText($card['card_type']); ?></td>
                        <td><?php echo $card['duration'] ? $card['duration'] . '天' : '-'; ?></td>
                        <td><?php echo getStatusText($card['status'], 'card'); ?></td>
                        <td>
                            <?php if ($card['device_id']): ?>
                            <small><?php echo h(substr($card['device_id'], 0, 16)); ?>...</small>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($card['created_at'], 'Y-m-d H:i'); ?></td>
                        <td><?php echo $card['activated_at'] ? formatDate($card['activated_at'], 'Y-m-d H:i') : '-'; ?></td>
                        <td><?php echo $card['expire_date'] ? formatDate($card['expire_date'], 'Y-m-d H:i') : '-'; ?></td>
                        <td>
                            <?php if ($card['status'] == 1 && $card['device_id']): ?>
                            <button onclick="unbindCard(<?php echo $card['id']; ?>)" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-unlock"></i>
                            </button>
                            <?php endif; ?>
                            <button onclick="deleteItem('delete_card', <?php echo $card['id']; ?>)" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
<?php } elseif ($action == 'generate') {
    // 检查是否为超级管理员
    $isSuperAdmin = ($admin['role'] === 'super_admin');
    
    // 根据权限获取项目列表
    if ($isSuperAdmin) {
        $projects = $db->fetchAll("SELECT * FROM projects WHERE status = 1 ORDER BY name");
    } else {
        $projects = $db->fetchAll("SELECT * FROM projects WHERE status = 1 AND admin_id = ? ORDER BY name", [$admin['id']]);
    }
    
    if (empty($projects)) {
        echo '<div class="alert alert-warning">请先创建项目</div>';
        return;
    }
    ?>
    
    <div class="content-card">
        <div class="content-card-header">
            <h5><i class="bi bi-plus-circle"></i> 批量生成卡密</h5>
            <a href="?page=cards" class="btn btn-outline-secondary">
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
            <input type="hidden" name="generate_cards" value="1">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">选择项目 *</label>
                    <select class="form-select" name="project_id" required>
                        <option value="">请选择...</option>
                        <?php foreach ($projects as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $projectId == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo h($p['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">生成数量 *</label>
                    <input type="number" class="form-control" name="count" min="1" max="1000" value="10" required>
                    <small class="text-muted">最多1000个</small>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">卡密类型 *</label>
                    <select class="form-select" name="card_type" id="cardType" onchange="toggleDuration()" required>
                        <option value="time">时长卡</option>
                        <option value="permanent">永久卡</option>
                        <option value="count">次数卡</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3" id="durationGroup">
                    <label class="form-label">时长（天）*</label>
                    <input type="number" class="form-control" name="duration" id="duration" min="1" value="30">
                    <small class="text-muted">激活后有效天数</small>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">最大使用次数</label>
                    <input type="number" class="form-control" name="max_use_count" min="1" value="1">
                    <small class="text-muted">一卡多用</small>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong>说明：</strong>
                <ul class="mb-0 mt-2">
                    <li>时长卡：激活后按天数计算有效期</li>
                    <li>永久卡：激活后永久有效</li>
                    <li>次数卡：可使用指定次数</li>
                    <li>生成的卡密将自动关联到选择的项目</li>
                </ul>
            </div>
            
            <div class="text-end">
                <a href="?page=cards" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-lightning"></i> 立即生成
                </button>
            </div>
        </form>
    </div>
    
    <script>
        function toggleDuration() {
            var type = document.getElementById('cardType').value;
            var durationGroup = document.getElementById('durationGroup');
            var duration = document.getElementById('duration');
            
            if (type == 'permanent') {
                durationGroup.style.display = 'none';
                duration.required = false;
            } else {
                durationGroup.style.display = 'block';
                duration.required = type == 'time';
            }
        }
    </script>
    
<?php } elseif ($action == 'view_generated') {
    if (!isset($_SESSION['generated_cards'])) {
        header('Location: ?page=cards');
        exit;
    }
    
    $generatedCards = $_SESSION['generated_cards'];
    unset($_SESSION['generated_cards']);
    ?>
    
    <div class="content-card">
        <div class="content-card-header">
            <h5><i class="bi bi-check-circle text-success"></i> 卡密生成成功</h5>
            <a href="?page=cards" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> 返回列表
            </a>
        </div>
        
        <div class="alert alert-success">
            <i class="bi bi-check-lg"></i> 成功生成 <strong><?php echo count($generatedCards); ?></strong> 个卡密
        </div>
        
        <div class="mb-3">
            <button class="btn btn-info" onclick="copyAllCards()">
                <i class="bi bi-clipboard"></i> 复制全部
            </button>
            <div class="btn-group">
                <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> 下载
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="downloadCards('txt')">
                        <i class="bi bi-filetype-txt"></i> TXT格式（仅卡密）
                    </a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="downloadCards('csv')">
                        <i class="bi bi-filetype-csv"></i> CSV格式（Excel）
                    </a></li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <textarea class="form-control" id="cardsText" rows="15" readonly><?php echo implode("\n", $generatedCards); ?></textarea>
            </div>
        </div>
    </div>
    
    <script>
        function copyAllCards() {
            var text = document.getElementById('cardsText');
            text.select();
            document.execCommand('copy');
            Swal.fire('成功', '已复制到剪贴板', 'success');
        }
        
        function downloadCards(format) {
            var text = document.getElementById('cardsText').value;
            var blob, filename, mimeType;
            
            if (format === 'csv') {
                // CSV格式：添加表头
                var csvContent = '\uFEFF'; // UTF-8 BOM
                csvContent += '卡密\n';
                csvContent += text.replace(/\n/g, '\n');
                blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
                filename = 'cards_' + Date.now() + '.csv';
            } else {
                // TXT格式
                blob = new Blob(['\uFEFF' + text], {type: 'text/plain;charset=utf-8;'});
                filename = 'cards_' + Date.now() + '.txt';
            }
            
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            Swal.fire('成功', '文件已下载', 'success');
        }
    </script>
    
<?php } ?>



