<?php
// 公共函数库
require_once 'config.php';
require_once 'database.php';

// 检查管理员登录状态
function checkLogin() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
        header('Location: index.php');
        exit;
    }
}

// 获取当前登录的管理员信息
function getCurrentAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    
    $db = Database::getInstance();
    return $db->fetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
}

// 记录操作日志
function logAction($action, $details = '', $projectId = null) {
    $db = Database::getInstance();
    $adminId = $_SESSION['admin_id'] ?? null;
    
    $data = [
        'admin_id' => $adminId,
        'project_id' => $projectId,
        'action' => $action,
        'details' => $details,
        'ip_address' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    
    return $db->insert('logs', $data);
}

// 记录卡密使用日志
function logCardUsage($cardKey, $projectId, $action, $deviceId = '', $result = '') {
    $db = Database::getInstance();
    
    $data = [
        'card_key' => $cardKey,
        'project_id' => $projectId,
        'device_id' => $deviceId,
        'action' => $action,
        'ip_address' => getClientIP(),
        'result' => $result
    ];
    
    return $db->insert('card_usage_logs', $data);
}

// 获取客户端IP
function getClientIP() {
    $ip = '';
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return trim($ip);
}

// 生成唯一卡密
function generateCardKey($prefix = CARD_PREFIX, $length = 16) {
    $db = Database::getInstance();
    
    do {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $key = $prefix . '-';
        $segmentLength = 4;
        $segments = ceil($length / $segmentLength);
        
        for ($i = 0; $i < $segments; $i++) {
            for ($j = 0; $j < $segmentLength; $j++) {
                $key .= $chars[random_int(0, strlen($chars) - 1)];
            }
            if ($i < $segments - 1) {
                $key .= '-';
            }
        }
        
        // 检查是否已存在
        $exists = $db->fetchOne("SELECT id FROM cards WHERE card_key = ?", [$key]);
    } while ($exists);
    
    return $key;
}

// 批量生成卡密
function generateBatchCards($projectId, $count, $type = 'time', $duration = 0, $maxUseCount = 1) {
    $db = Database::getInstance();
    $cards = [];
    
    $db->beginTransaction();
    
    try {
        for ($i = 0; $i < $count; $i++) {
            $cardKey = generateCardKey();
            
            $data = [
                'project_id' => $projectId,
                'card_key' => $cardKey,
                'card_type' => $type,
                'duration' => $duration,
                'status' => 0, // 0=未使用
                'max_use_count' => $maxUseCount
            ];
            
            $cardId = $db->insert('cards', $data);
            
            if ($cardId) {
                $cards[] = $cardKey;
            } else {
                throw new Exception('生成卡密失败');
            }
        }
        
        // 更新项目统计
        $db->query("UPDATE projects SET total_cards = total_cards + ?, updated_at = datetime('now', 'localtime') WHERE id = ?", [$count, $projectId]);
        
        $db->commit();
        return $cards;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

// 激活卡密
function activateCard($cardKey, $username, $deviceModel = '', $deviceInfo = '') {
    $db = Database::getInstance();
    
    // 获取卡密信息
    $card = $db->fetchOne("SELECT * FROM cards WHERE card_key = ?", [$cardKey]);
    
    if (!$card) {
        return ['success' => false, 'message' => '卡密不存在'];
    }
    
    if ($card['status'] == 2) {
        return ['success' => false, 'message' => '卡密已被禁用'];
    }
    
    // 检查使用次数
    if ($card['use_count'] >= $card['max_use_count']) {
        return ['success' => false, 'message' => '卡密使用次数已达上限'];
    }
    
    // 检查用户绑定
    if ($card['status'] == 1 && $card['device_id'] && $card['device_id'] != $username) {
        return ['success' => false, 'message' => '卡密已绑定其他用户'];
    }
    
    $db->beginTransaction();
    
    try {
        $now = date('Y-m-d H:i:s');
        $expireTime = null;
        
        // 计算过期时间
        if ($card['card_type'] == 'time' && $card['duration'] > 0) {
            if ($card['status'] == 0) {
                // 首次激活
                $expireTime = date('Y-m-d H:i:s', strtotime("+{$card['duration']} days"));
            } else {
                // 已激活，使用原有过期时间
                $expireTime = $card['expire_date'];
            }
        }
        
        // 更新卡密状态（device_id 字段现在存储 username）
        $updateData = [
            'status' => 1,
            'device_id' => $username,
            'device_model' => $deviceModel,
            'use_count' => $card['use_count'] + 1,
            'updated_at' => $now
        ];
        
        if ($card['status'] == 0) {
            $updateData['activated_at'] = $now;
            if ($expireTime) {
                $updateData['expire_date'] = $expireTime;
            }
            
            // 更新项目已使用卡密数
            $db->query("UPDATE projects SET used_cards = used_cards + 1 WHERE id = ?", [$card['project_id']]);
        }
        
        $db->update('cards', $updateData, 'id = ?', [$card['id']]);
        
        // 创建或更新用户记录（device_id 字段存储 username）
        $user = $db->fetchOne("SELECT * FROM users WHERE project_id = ? AND device_id = ?", [$card['project_id'], $username]);
        
        if ($user) {
            $db->update('users', [
                'card_key' => $cardKey,
                'device_model' => $deviceModel,
                'device_info' => $deviceInfo,
                'expire_time' => $expireTime,
                'status' => 1,
                'last_active' => $now,
                'updated_at' => $now
            ], 'id = ?', [$user['id']]);
        } else {
            $db->insert('users', [
                'project_id' => $card['project_id'],
                'card_key' => $cardKey,
                'device_id' => $username,
                'device_model' => $deviceModel,
                'device_info' => $deviceInfo,
                'expire_time' => $expireTime,
                'status' => 1,
                'last_active' => $now
            ]);
        }
        
        // 记录日志
        logCardUsage($cardKey, $card['project_id'], 'activate', $username, 'success');
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => '激活成功',
            'data' => [
                'card_key' => $cardKey,
                'username' => $username,
                'expire_time' => $expireTime,
                'card_type' => $card['card_type'],
                'status' => 1
            ]
        ];
    } catch (Exception $e) {
        $db->rollBack();
        logCardUsage($cardKey, $card['project_id'], 'activate', $username, 'failed: ' . $e->getMessage());
        return ['success' => false, 'message' => '激活失败：' . $e->getMessage()];
    }
}

// 验证卡密状态
function verifyCard($cardKey, $username, $projectId = null) {
    $db = Database::getInstance();
    
    // 获取卡密信息（如果指定了项目ID，则验证卡密属于该项目）
    $sql = "SELECT c.*, p.status as project_status FROM cards c LEFT JOIN projects p ON c.project_id = p.id WHERE c.card_key = ?";
    $params = [$cardKey];
    
    if ($projectId !== null) {
        $sql .= " AND c.project_id = ?";
        $params[] = $projectId;
    }
    
    $card = $db->fetchOne($sql, $params);
    
    if (!$card) {
        return ['success' => false, 'message' => '卡密不存在或不属于该项目'];
    }
    
    if ($card['project_status'] != 1) {
        return ['success' => false, 'message' => '项目已禁用'];
    }
    
    if ($card['status'] == 0) {
        return ['success' => false, 'message' => '卡密未激活'];
    }
    
    if ($card['status'] == 2) {
        return ['success' => false, 'message' => '卡密已禁用'];
    }
    
    if ($card['device_id'] != $username) {
        return ['success' => false, 'message' => '用户不匹配'];
    }
    
    // 检查是否过期
    if ($card['expire_date']) {
        if (strtotime($card['expire_date']) < time()) {
            // 更新卡密状态为禁用
            $db->update('cards', ['status' => 2], 'id = ?', [$card['id']]);
            return ['success' => false, 'message' => '卡密已过期'];
        }
    }
    
    // 更新最后活跃时间
    $db->update('users', [
        'last_active' => date('Y-m-d H:i:s')
    ], 'project_id = ? AND device_id = ?', [$card['project_id'], $username]);
    
    $remainingDays = null;
    if ($card['expire_date']) {
        $remainingDays = ceil((strtotime($card['expire_date']) - time()) / 86400);
    }
    
    return [
        'success' => true,
        'message' => '验证成功',
        'data' => [
            'card_key' => $cardKey,
            'username' => $username,
            'status' => $card['status'],
            'expire_time' => $card['expire_date'],
            'remaining_days' => $remainingDays,
            'card_type' => $card['card_type']
        ]
    ];
}

// 安全的输出HTML
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// JSON响应
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 格式化日期
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// 时间差描述
function timeAgo($datetime) {
    if (empty($datetime)) return '-';
    
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . '秒前';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . '天前';
    } else {
        return date('Y-m-d H:i', $time);
    }
}

// 状态文本
function getStatusText($status, $type = 'card') {
    if ($type == 'card') {
        $statuses = [
            0 => '<span class="badge bg-secondary">未使用</span>',
            1 => '<span class="badge bg-success">已激活</span>',
            2 => '<span class="badge bg-danger">已禁用</span>'
        ];
    } elseif ($type == 'project') {
        $statuses = [
            0 => '<span class="badge bg-secondary">已禁用</span>',
            1 => '<span class="badge bg-success">启用中</span>'
        ];
    } else {
        $statuses = [
            0 => '<span class="badge bg-secondary">禁用</span>',
            1 => '<span class="badge bg-success">启用</span>'
        ];
    }
    
    return $statuses[$status] ?? '<span class="badge bg-secondary">未知</span>';
}

// 卡密类型文本
function getCardTypeText($type) {
    $types = [
        'time' => '时长卡',
        'permanent' => '永久卡',
        'count' => '次数卡'
    ];
    return $types[$type] ?? '未知';
}

// 验证API Key
function verifyApiToken($token) {
    $db = Database::getInstance();
    
    $apiToken = $db->fetchOne("SELECT * FROM api_tokens WHERE token = ? AND status = 1", [$token]);
    
    if (!$apiToken) {
        return false;
    }
    
    // 检查是否过期
    if ($apiToken['expire_at'] && strtotime($apiToken['expire_at']) < time()) {
        return false;
    }
    
    // 更新最后使用时间
    $db->update('api_tokens', [
        'last_used' => date('Y-m-d H:i:s')
    ], 'id = ?', [$apiToken['id']]);
    
    return $apiToken;
}

// 生成API Key
function generateApiToken($projectId, $name = '', $expireTime = null) {
    $db = Database::getInstance();
    
    do {
        $token = bin2hex(random_bytes(32));
        $exists = $db->fetchOne("SELECT id FROM api_tokens WHERE token = ?", [$token]);
    } while ($exists);
    
    $data = [
        'project_id' => $projectId,
        'token' => $token,
        'name' => $name,
        'status' => 1
    ];
    
    // 如果指定了过期时间，则设置；否则永不过期（null）
    if ($expireTime !== null) {
        $data['expire_at'] = $expireTime;
    } else {
        $data['expire_at'] = null; // 永不过期
    }
    
    $id = $db->insert('api_tokens', $data);
    
    return $id ? $token : false;
}

// 分页函数
function paginate($total, $page, $pageSize = PAGE_SIZE) {
    $totalPages = ceil($total / $pageSize);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $pageSize;
    
    return [
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages
    ];
}

// 导出卡密为CSV
function exportCardsToCSV($cards) {
    // 清除之前的输出缓冲
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // 强制下载，防止浏览器预览（使用 octet-stream 强制下载）
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="cards_' . date('YmdHis') . '.csv"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    // 写入表头
    fputcsv($output, ['卡密', '类型', '时长(天)', '状态', '设备ID', '创建时间', '激活时间', '过期时间']);
    
    // 写入数据
    foreach ($cards as $card) {
        fputcsv($output, [
            $card['card_key'],
            getCardTypeText($card['card_type']),
            $card['duration'],
            $card['status'] == 0 ? '未使用' : ($card['status'] == 1 ? '已激活' : '已禁用'),
            $card['device_id'] ?? '',
            $card['created_at'],
            $card['activated_at'] ?? '',
            $card['expire_date'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// 导出卡密到TXT（每行一个卡密）
function exportCardsToTXT($cards, $format = 'simple') {
    // 清除之前的输出缓冲
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // 强制下载，防止浏览器预览（使用 octet-stream 强制下载）
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="cards_' . date('YmdHis') . '.txt"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo chr(0xEF).chr(0xBB).chr(0xBF); // UTF-8 BOM
    
    if ($format == 'simple') {
        // 简单格式：每行一个卡密
        foreach ($cards as $card) {
            echo $card['card_key'] . "\r\n";
        }
    } elseif ($format == 'detailed') {
        // 详细格式：包含卡密信息
        echo "========== 卡密导出 ==========" . "\r\n";
        echo "导出时间：" . date('Y-m-d H:i:s') . "\r\n";
        echo "卡密数量：" . count($cards) . "\r\n";
        echo "=============================" . "\r\n\r\n";
        
        foreach ($cards as $card) {
            echo "卡密：" . $card['card_key'] . "\r\n";
            echo "类型：" . getCardTypeText($card['card_type']) . "\r\n";
            echo "时长：" . $card['duration'] . " 天\r\n";
            echo "状态：" . ($card['status'] == 0 ? '未使用' : ($card['status'] == 1 ? '已激活' : '已禁用')) . "\r\n";
            if ($card['device_id']) {
                echo "绑定用户：" . $card['device_id'] . "\r\n";
            }
            echo "创建时间：" . $card['created_at'] . "\r\n";
            if ($card['activated_at']) {
                echo "激活时间：" . $card['activated_at'] . "\r\n";
            }
            if ($card['expire_date']) {
                echo "过期时间：" . $card['expire_date'] . "\r\n";
            }
            echo "-----------------------------" . "\r\n";
        }
    }
    
    exit;
}
?>



