<?php
// API接口 - 供APK应用调用
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 获取请求数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    $data = $_POST;
}

// 获取请求的动作
$action = $data['action'] ?? $_GET['action'] ?? '';

// API响应函数
function apiResponse($success, $message, $data = null, $code = 200) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => time(),
        'data' => $data
    ];
    
    http_response_code($code);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证API Key
function checkApiToken($data) {
    $token = $data['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }
    
    if (empty($token)) {
        apiResponse(false, 'API Key缺失', null, 401);
    }
    
    $apiToken = verifyApiToken($token);
    
    if (!$apiToken) {
        apiResponse(false, 'API Key无效或已过期', null, 401);
    }
    
    return $apiToken;
}

// 根据不同的动作执行相应操作
switch ($action) {
    case 'register':
        // 用户注册
        handleRegister($data);
        break;
        
    case 'login':
        // 用户登录
        handleLogin($data);
        break;
        
    case 'activate':
        // 激活卡密
        handleActivate($data);
        break;
        
    case 'verify':
        // 验证卡密
        handleVerify($data);
        break;
        
    case 'heartbeat':
        // 心跳检测
        handleHeartbeat($data);
        break;
        
    case 'get_card_info':
        // 获取卡密信息
        handleGetCardInfo($data);
        break;
        
    case 'get_user_info':
        // 获取用户信息（需要token）
        handleGetUserInfo($data);
        break;
        
    case 'unbind_device':
        // 解绑设备（需要API Key验证）
        handleUnbindDevice($data);
        break;
        
    default:
        apiResponse(false, '未知的操作', null, 400);
}

// 处理激活卡密
function handleActivate($data) {
    // 验证API Key
    $apiToken = checkApiToken($data);
    
    $cardKey = $data['card_key'] ?? '';
    $username = $data['username'] ?? '';
    $deviceModel = $data['device_model'] ?? '';
    $deviceInfo = $data['device_info'] ?? '';
    
    if (empty($cardKey) || empty($username)) {
        apiResponse(false, '参数缺失：card_key 和 username 为必填项');
    }
    
    $result = activateCard($cardKey, $username, $deviceModel, json_encode($deviceInfo), $apiToken['project_id']);
    
    if ($result['success']) {
        apiResponse(true, $result['message'], $result['data']);
    } else {
        apiResponse(false, $result['message']);
    }
}

// 处理验证卡密
function handleVerify($data) {
    // 验证API Key
    $apiToken = checkApiToken($data);
    
    $cardKey = $data['card_key'] ?? '';
    $username = $data['username'] ?? '';
    
    if (empty($cardKey) || empty($username)) {
        apiResponse(false, '参数缺失：card_key 和 username 为必填项');
    }
    
    $result = verifyCard($cardKey, $username, $apiToken['project_id']);
    
    if ($result['success']) {
        apiResponse(true, $result['message'], $result['data']);
    } else {
        apiResponse(false, $result['message']);
    }
}

// 处理心跳检测
function handleHeartbeat($data) {
    // 验证API Key
    $apiToken = checkApiToken($data);
    
    $cardKey = $data['card_key'] ?? '';
    $username = $data['username'] ?? '';
    
    if (empty($cardKey) || empty($username)) {
        apiResponse(false, '参数缺失：card_key 和 username 为必填项');
    }
    
    $db = Database::getInstance();
    
    // 更新用户最后活跃时间（验证卡密属于该项目）
    $user = $db->fetchOne("SELECT u.*, c.status as card_status, c.expire_date, c.project_id FROM users u LEFT JOIN cards c ON u.card_key = c.card_key WHERE u.device_id = ? AND u.card_key = ? AND c.project_id = ?", [$username, $cardKey, $apiToken['project_id']]);
    
    if (!$user) {
        apiResponse(false, '用户不存在或卡密不属于该项目');
    }
    
    if ($user['card_status'] != 1) {
        apiResponse(false, '卡密已失效');
    }
    
    // 检查是否过期
    if ($user['expire_date'] && strtotime($user['expire_date']) < time()) {
        $db->update('cards', ['status' => 2], 'card_key = ?', [$cardKey]);
        $db->update('users', ['status' => 0], 'id = ?', [$user['id']]);
        apiResponse(false, '卡密已过期');
    }
    
    $db->update('users', [
        'last_active' => date('Y-m-d H:i:s')
    ], 'id = ?', [$user['id']]);
    
    $remainingDays = null;
    if ($user['expire_date']) {
        $remainingDays = ceil((strtotime($user['expire_date']) - time()) / 86400);
    }
    
    apiResponse(true, '心跳成功', [
        'status' => 1,
        'expire_time' => $user['expire_date'],
        'remaining_days' => $remainingDays
    ]);
}

// 获取卡密信息
function handleGetCardInfo($data) {
    // 验证API Key
    $apiToken = checkApiToken($data);
    
    $cardKey = $data['card_key'] ?? '';
    
    if (empty($cardKey)) {
        apiResponse(false, '卡密不能为空');
    }
    
    $db = Database::getInstance();
    $card = $db->fetchOne("SELECT c.*, p.name as project_name FROM cards c LEFT JOIN projects p ON c.project_id = p.id WHERE c.card_key = ? AND c.project_id = ?", [$cardKey, $apiToken['project_id']]);
    
    if (!$card) {
        apiResponse(false, '卡密不存在或不属于该项目');
    }
    
    $info = [
        'card_key' => $card['card_key'],
        'project_name' => $card['project_name'],
        'card_type' => $card['card_type'],
        'duration' => $card['duration'],
        'status' => $card['status'],
        'is_activated' => $card['status'] == 1,
        'is_bound' => !empty($card['device_id']),
        'created_at' => $card['created_at']
    ];
    
    if ($card['status'] == 1) {
        $info['activated_at'] = $card['activated_at'];
        $info['expire_date'] = $card['expire_date'];
        
        if ($card['expire_date']) {
            $info['remaining_days'] = ceil((strtotime($card['expire_date']) - time()) / 86400);
        }
    }
    
    apiResponse(true, '获取成功', $info);
}

// 解绑设备（需要管理员权限或API Key）
function handleUnbindDevice($data) {
    $apiToken = checkApiToken($data);
    
    $cardKey = $data['card_key'] ?? '';
    
    if (empty($cardKey)) {
        apiResponse(false, '卡密不能为空');
    }
    
    $db = Database::getInstance();
    
    // 验证卡密是否属于该项目
    $card = $db->fetchOne("SELECT * FROM cards WHERE card_key = ? AND project_id = ?", [$cardKey, $apiToken['project_id']]);
    
    if (!$card) {
        apiResponse(false, '卡密不存在或不属于该项目');
    }
    
    $db->beginTransaction();
    
    try {
        // 重置卡密设备绑定
        $db->update('cards', [
            'device_id' => null,
            'device_model' => null,
            'status' => 0,
            'use_count' => 0,
            'activated_at' => null,
            'expire_date' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'card_key = ?', [$cardKey]);
        
        // 删除用户记录
        $db->delete('users', 'card_key = ?', [$cardKey]);
        
        // 记录日志
        logCardUsage($cardKey, $card['project_id'], 'unbind', '', 'success via API');
        
        $db->commit();
        
        apiResponse(true, '解绑成功');
    } catch (Exception $e) {
        $db->rollBack();
        apiResponse(false, '解绑失败：' . $e->getMessage());
    }
}

// 处理用户注册
function handleRegister($data) {
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $deviceId = $data['device_id'] ?? '';
    $deviceModel = $data['device_model'] ?? '';
    
    // 验证API Key
    $apiToken = checkApiToken($data);
    
    if (empty($username) || empty($password)) {
        apiResponse(false, '用户名和密码不能为空');
    }
    
    // 验证用户名长度
    if (strlen($username) < 3 || strlen($username) > 20) {
        apiResponse(false, '用户名长度必须在3-20个字符之间');
    }
    
    // 验证密码长度
    if (strlen($password) < 6) {
        apiResponse(false, '密码长度不能少于6位');
    }
    
    // 验证用户名格式（只允许字母、数字、下划线）
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        apiResponse(false, '用户名只能包含字母、数字和下划线');
    }
    
    $db = Database::getInstance();
    
    // 检查用户名是否已存在（在同一项目内）
    $exists = $db->fetchOne("SELECT id FROM app_users WHERE username = ? AND project_id = ?", [$username, $apiToken['project_id']]);
    if ($exists) {
        apiResponse(false, '用户名已存在');
    }
    
    // 检查邮箱是否已被使用（在同一项目内）
    if ($email) {
        $emailExists = $db->fetchOne("SELECT id FROM app_users WHERE email = ? AND project_id = ?", [$email, $apiToken['project_id']]);
        if ($emailExists) {
            apiResponse(false, '邮箱已被使用');
        }
    }
    
    // 检查手机号是否已被使用（在同一项目内）
    if ($phone) {
        $phoneExists = $db->fetchOne("SELECT id FROM app_users WHERE phone = ? AND project_id = ?", [$phone, $apiToken['project_id']]);
        if ($phoneExists) {
            apiResponse(false, '手机号已被使用');
        }
    }
    
    // 生成用户token
    $token = bin2hex(random_bytes(32));
    
    $db->beginTransaction();
    
    try {
        // 验证项目是否存在
        $project = $db->fetchOne("SELECT * FROM projects WHERE id = ? AND status = 1", [$apiToken['project_id']]);
        if (!$project) {
            apiResponse(false, '项目不存在或已禁用');
        }
        
        // 创建用户
        $userId = $db->insert('app_users', [
            'project_id' => $apiToken['project_id'],
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'email' => $email,
            'phone' => $phone,
            'device_id' => $deviceId,
            'device_model' => $deviceModel,
            'token' => $token,
            'token_expire' => date('Y-m-d H:i:s', time() + 86400 * 30), // 30天有效
            'status' => 1,
            'last_login' => date('Y-m-d H:i:s')
        ]);
        
        $db->commit();
        
        apiResponse(true, '注册成功', [
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'token' => $token,
            'token_expire' => date('Y-m-d H:i:s', time() + 86400 * 30)
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        apiResponse(false, '注册失败：' . $e->getMessage());
    }
}

// 处理用户登录
function handleLogin($data) {
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $deviceId = $data['device_id'] ?? '';
    $deviceModel = $data['device_model'] ?? '';
    
    if (empty($username) || empty($password)) {
        apiResponse(false, '用户名和密码不能为空');
    }
    
    $db = Database::getInstance();
    
    // 查找用户
    $user = $db->fetchOne("SELECT * FROM app_users WHERE username = ? OR email = ? OR phone = ?", [$username, $username, $username]);
    
    if (!$user) {
        apiResponse(false, '用户不存在');
    }
    
    // 验证密码
    if (!password_verify($password, $user['password'])) {
        apiResponse(false, '密码错误');
    }
    
    // 检查账户状态
    if ($user['status'] != 1) {
        apiResponse(false, '账户已被禁用');
    }
    
    // 生成新token
    $token = bin2hex(random_bytes(32));
    $tokenExpire = date('Y-m-d H:i:s', time() + 86400 * 30); // 30天有效
    
    $db->beginTransaction();
    
    try {
        // 更新用户信息
        $db->update('app_users', [
            'token' => $token,
            'token_expire' => $tokenExpire,
            'device_id' => $deviceId,
            'device_model' => $deviceModel,
            'last_login' => date('Y-m-d H:i:s'),
            'login_count' => $user['login_count'] + 1
        ], 'id = ?', [$user['id']]);
        
        $db->commit();
        
        apiResponse(true, '登录成功', [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'token' => $token,
            'token_expire' => $tokenExpire,
            'vip_level' => $user['vip_level'],
            'balance' => $user['balance']
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        apiResponse(false, '登录失败：' . $e->getMessage());
    }
}

// 获取用户信息（需要token验证）
function handleGetUserInfo($data) {
    $token = $data['token'] ?? '';
    
    if (empty($token)) {
        apiResponse(false, 'Token不能为空', null, 401);
    }
    
    $db = Database::getInstance();
    
    // 验证token
    $user = $db->fetchOne("SELECT * FROM app_users WHERE token = ? AND status = 1", [$token]);
    
    if (!$user) {
        apiResponse(false, 'Token无效', null, 401);
    }
    
    // 检查token是否过期
    if ($user['token_expire'] && strtotime($user['token_expire']) < time()) {
        apiResponse(false, 'Token已过期，请重新登录', null, 401);
    }
    
    // 查询用户的卡密信息
    $cards = $db->fetchAll("SELECT c.*, p.name as project_name FROM cards c LEFT JOIN projects p ON c.project_id = p.id WHERE c.device_id = ? ORDER BY c.activated_at DESC", [$user['device_id']]);
    
    apiResponse(true, '获取成功', [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'vip_level' => $user['vip_level'],
        'balance' => $user['balance'],
        'device_id' => $user['device_id'],
        'device_model' => $user['device_model'],
        'register_time' => $user['created_at'],
        'last_login' => $user['last_login'],
        'login_count' => $user['login_count'],
        'cards' => $cards
    ]);
}
?>

