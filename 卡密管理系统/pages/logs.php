<?php
// 操作日志页面
if (!defined('DB_FILE')) exit;

$type = $_GET['type'] ?? 'admin';

// 检查是否为超级管理员
$isSuperAdmin = ($admin['role'] === 'super_admin');

if ($type == 'admin') {
    // 管理员操作日志
    if ($isSuperAdmin) {
        // 超级管理员看所有日志
        $logs = $db->fetchAll("SELECT l.*, a.username, p.name as project_name 
            FROM logs l 
            LEFT JOIN admins a ON l.admin_id = a.id 
            LEFT JOIN projects p ON l.project_id = p.id 
            ORDER BY l.id DESC 
            LIMIT 500");
    } else {
        // 普通管理员只看自己的操作日志
        $logs = $db->fetchAll("SELECT l.*, a.username, p.name as project_name 
            FROM logs l 
            LEFT JOIN admins a ON l.admin_id = a.id 
            LEFT JOIN projects p ON l.project_id = p.id 
            WHERE l.admin_id = ?
            ORDER BY l.id DESC 
            LIMIT 500", [$admin['id']]);
    }
    ?>
    
    <div class="content-card">
        <div class="content-card-header">
            <h5><i class="bi bi-clock-history"></i> 管理员操作日志</h5>
            <div>
                <a href="?page=logs&type=admin" class="btn btn-sm <?php echo $type == 'admin' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    管理员日志
                </a>
                <a href="?page=logs&type=card" class="btn btn-sm <?php echo $type == 'card' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    卡密使用日志
                </a>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover table-sm data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>操作</th>
                        <th>详情</th>
                        <th>管理员</th>
                        <th>项目</th>
                        <th>IP地址</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo $log['id']; ?></td>
                        <td><?php echo h($log['action']); ?></td>
                        <td><small><?php echo h($log['details']); ?></small></td>
                        <td><?php echo h($log['username'] ?? '系统'); ?></td>
                        <td><?php echo h($log['project_name'] ?? '-'); ?></td>
                        <td><?php echo h($log['ip_address']); ?></td>
                        <td><?php echo timeAgo($log['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
<?php } else {
    // 卡密使用日志
    if ($isSuperAdmin) {
        // 超级管理员看所有卡密使用日志
        $cardLogs = $db->fetchAll("SELECT l.*, p.name as project_name 
            FROM card_usage_logs l 
            LEFT JOIN projects p ON l.project_id = p.id 
            ORDER BY l.id DESC 
            LIMIT 1000");
    } else {
        // 普通管理员只看自己项目的卡密使用日志
        $cardLogs = $db->fetchAll("SELECT l.*, p.name as project_name 
            FROM card_usage_logs l 
            LEFT JOIN projects p ON l.project_id = p.id 
            WHERE p.admin_id = ?
            ORDER BY l.id DESC 
            LIMIT 1000", [$admin['id']]);
    }
    ?>
    
    <div class="content-card">
        <div class="content-card-header">
            <h5><i class="bi bi-credit-card"></i> 卡密使用日志</h5>
            <div>
                <a href="?page=logs&type=admin" class="btn btn-sm <?php echo $type == 'admin' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    管理员日志
                </a>
                <a href="?page=logs&type=card" class="btn btn-sm <?php echo $type == 'card' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    卡密使用日志
                </a>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover table-sm data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>卡密</th>
                        <th>项目</th>
                        <th>操作</th>
                        <th>设备ID</th>
                        <th>结果</th>
                        <th>IP地址</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cardLogs as $log): ?>
                    <tr>
                        <td><?php echo $log['id']; ?></td>
                        <td><code><?php echo h($log['card_key']); ?></code></td>
                        <td><?php echo h($log['project_name']); ?></td>
                        <td><?php echo h($log['action']); ?></td>
                        <td><small><?php echo h(substr($log['device_id'], 0, 16)); ?>...</small></td>
                        <td>
                            <?php if (strpos($log['result'], 'success') !== false): ?>
                            <span class="badge bg-success">成功</span>
                            <?php else: ?>
                            <span class="badge bg-danger">失败</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($log['ip_address']); ?></td>
                        <td><?php echo timeAgo($log['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
<?php } ?>



