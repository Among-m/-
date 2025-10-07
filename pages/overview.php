<?php
// 数据概览页面
if (!defined('DB_FILE')) exit;

// 检查是否为超级管理员
$isSuperAdmin = ($admin['role'] === 'super_admin');

// 获取最近的日志
if ($isSuperAdmin) {
    $recentLogs = $db->fetchAll("SELECT l.*, a.username FROM logs l LEFT JOIN admins a ON l.admin_id = a.id ORDER BY l.created_at DESC LIMIT 10");
} else {
    $recentLogs = $db->fetchAll("SELECT l.*, a.username FROM logs l LEFT JOIN admins a ON l.admin_id = a.id WHERE l.admin_id = ? ORDER BY l.created_at DESC LIMIT 10", [$admin['id']]);
}

// 获取最近激活的卡密
if ($isSuperAdmin) {
    $recentCards = $db->fetchAll("SELECT c.*, p.name as project_name FROM cards c LEFT JOIN projects p ON c.project_id = p.id WHERE c.status = 1 ORDER BY c.activated_at DESC LIMIT 10");
} else {
    $recentCards = $db->fetchAll("SELECT c.*, p.name as project_name FROM cards c LEFT JOIN projects p ON c.project_id = p.id WHERE c.status = 1 AND p.admin_id = ? ORDER BY c.activated_at DESC LIMIT 10", [$admin['id']]);
}

// 获取今日数据
$todayStart = date('Y-m-d 00:00:00');
if ($isSuperAdmin) {
    $todayStats = [
        'activated_cards' => $db->count('cards', "activated_at >= ?", [$todayStart]),
        'new_users' => $db->count('app_users', "created_at >= ?", [$todayStart])
    ];
} else {
    $todayStats = [
        'activated_cards' => $db->count('cards', "activated_at >= ? AND project_id IN (SELECT id FROM projects WHERE admin_id = ?)", [$todayStart, $admin['id']]),
        'new_users' => $db->count('app_users', "created_at >= ? AND project_id IN (SELECT id FROM projects WHERE admin_id = ?)", [$todayStart, $admin['id']])
    ];
}
?>

<div class="row">
    <!-- 统计卡片 -->
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-folder text-white"></i>
            </div>
            <p>项目总数</p>
            <h3><?php echo number_format($stats['total_projects']); ?></h3>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-credit-card text-white"></i>
            </div>
            <p>卡密总数</p>
            <h3><?php echo number_format($stats['total_cards']); ?></h3>
            <small class="text-muted">已使用: <?php echo $stats['used_cards']; ?></small>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="bi bi-people text-white"></i>
            </div>
            <p>用户总数</p>
            <h3><?php echo number_format($stats['total_users']); ?></h3>
            <small class="text-muted">活跃: <?php echo $stats['active_users']; ?></small>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="bi bi-graph-up text-white"></i>
            </div>
            <p>今日激活</p>
            <h3><?php echo number_format($todayStats['activated_cards']); ?></h3>
            <small class="text-muted">新增用户: <?php echo $todayStats['new_users']; ?></small>
        </div>
    </div>
</div>

<div class="row">
    <!-- 最近激活的卡密 -->
    <div class="col-md-6 mb-4">
        <div class="content-card">
            <div class="content-card-header">
                <h5><i class="bi bi-credit-card"></i> 最近激活的卡密</h5>
                <a href="?page=cards" class="btn btn-sm btn-outline-primary">查看全部</a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>卡密</th>
                            <th>项目</th>
                            <th>激活时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentCards): ?>
                            <?php foreach ($recentCards as $card): ?>
                            <tr>
                                <td><code><?php echo h($card['card_key']); ?></code></td>
                                <td><?php echo h($card['project_name']); ?></td>
                                <td><?php echo timeAgo($card['activated_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">暂无数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- 最近操作日志 -->
    <div class="col-md-6 mb-4">
        <div class="content-card">
            <div class="content-card-header">
                <h5><i class="bi bi-clock-history"></i> 最近操作日志</h5>
                <a href="?page=logs" class="btn btn-sm btn-outline-primary">查看全部</a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>操作</th>
                            <th>管理员</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentLogs): ?>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?php echo h($log['action']); ?></td>
                                <td><?php echo h($log['username'] ?? '系统'); ?></td>
                                <td><?php echo timeAgo($log['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">暂无数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 快捷操作 -->
<div class="row">
    <div class="col-12">
        <div class="content-card">
            <div class="content-card-header">
                <h5><i class="bi bi-lightning"></i> 快捷操作</h5>
            </div>
            
            <div class="row text-center">
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="?page=projects&action=add" class="btn btn-lg btn-outline-primary w-100">
                        <i class="bi bi-folder-plus d-block fs-1"></i>
                        <div class="mt-2">创建项目</div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="?page=cards&action=generate" class="btn btn-lg btn-outline-success w-100">
                        <i class="bi bi-credit-card-2-front d-block fs-1"></i>
                        <div class="mt-2">生成卡密</div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="?page=api" class="btn btn-lg btn-outline-info w-100">
                        <i class="bi bi-key d-block fs-1"></i>
                        <div class="mt-2">API Key</div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <a href="?page=logs" class="btn btn-lg btn-outline-warning w-100">
                        <i class="bi bi-graph-up d-block fs-1"></i>
                        <div class="mt-2">查看报表</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>



