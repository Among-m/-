<?php
// API对接文档页面
if (!defined('DB_FILE')) exit;

// 获取当前管理员的项目（用于文档示例）
$isSuperAdmin = ($admin['role'] === 'super_admin');
if ($isSuperAdmin) {
    $projects = $db->fetchAll("SELECT p.*, 
        (SELECT token FROM api_tokens WHERE project_id = p.id ORDER BY created_at DESC LIMIT 1) as api_token
        FROM projects p WHERE status = 1 ORDER BY name LIMIT 5");
} else {
    $projects = $db->fetchAll("SELECT p.*, 
        (SELECT token FROM api_tokens WHERE project_id = p.id ORDER BY created_at DESC LIMIT 1) as api_token
        FROM projects p WHERE status = 1 AND admin_id = ? ORDER BY name LIMIT 5", [$admin['id']]);
}

$exampleProject = $projects[0] ?? null;
$exampleToken = $exampleProject['api_token'] ?? 'your_api_token_here';
?>

<style>
.api-doc {
    font-family: 'Microsoft YaHei', Arial, sans-serif;
}
.api-endpoint {
    background: #f8f9fa;
    border-left: 4px solid #667eea;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}
.method-badge {
    padding: 4px 12px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 12px;
    margin-right: 10px;
}
.method-post { background: #28a745; color: white; }
.method-get { background: #007bff; color: white; }
.code-block {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    border-radius: 5px;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 13px;
    overflow-x: auto;
    margin: 10px 0;
}
.nav-tabs .nav-link {
    color: #495057;
}
.nav-tabs .nav-link.active {
    color: #667eea;
    border-color: #dee2e6 #dee2e6 #fff;
    font-weight: bold;
}
.response-example {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin: 10px 0;
}
.param-table {
    font-size: 14px;
}
.param-table th {
    background: #f8f9fa;
    font-weight: 600;
}
.badge-required {
    background: #dc3545;
    color: white;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 3px;
}
.badge-optional {
    background: #6c757d;
    color: white;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 3px;
}
</style>

<div class="api-doc">
    <!-- 页面标题 -->
    <div class="content-card mb-4">
        <div class="content-card-header">
            <h5><i class="bi bi-book"></i> API对接文档</h5>
            <div>
                <a href="api_test_example.html" target="_blank" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-play-circle"></i> 在线测试
                </a>
            </div>
        </div>
        
        <div class="p-4">
            <div class="alert alert-info">
                <h6><i class="bi bi-info-circle-fill"></i> 快速开始</h6>
                <p class="mb-2">本系统提供完整的卡密管理API接口，支持卡密激活、验证、心跳等功能。所有接口均返回JSON格式数据。</p>
                <ul class="mb-0">
                    <li><strong>接口地址：</strong><code><?php echo SITE_URL; ?>/api.php</code></li>
                    <li><strong>请求方式：</strong>支持 POST 和 GET</li>
                    <li><strong>数据格式：</strong>JSON / URL参数</li>
                    <li><strong>字符编码：</strong>UTF-8</li>
                </ul>
            </div>
            
            <?php if ($exampleProject): ?>
            <div class="alert alert-success">
                <h6><i class="bi bi-key-fill"></i> 您的API Key</h6>
                <p class="mb-2">项目：<strong><?php echo h($exampleProject['name']); ?></strong></p>
                <div class="input-group">
                    <input type="text" class="form-control" id="userApiToken" value="<?php echo h($exampleToken); ?>" readonly style="font-family: monospace;">
                    <button class="btn btn-success" onclick="copyToClipboard('userApiToken', this)">
                        <i class="bi bi-clipboard"></i> 复制令牌
                    </button>
                </div>
                <small class="text-muted mt-2 d-block">
                    <i class="bi bi-arrow-right"></i> 前往 <a href="?page=projects">项目管理</a> 查看所有令牌
                </small>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> 您还没有创建项目，请先前往 <a href="?page=projects">项目管理</a> 创建项目并获取API Key。
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- API接口列表 -->
    <div class="content-card mb-4">
        <div class="content-card-header">
            <h5><i class="bi bi-list-ul"></i> 接口列表</h5>
        </div>
        
        <div class="p-4">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-person-plus" style="font-size: 32px; color: #667eea;"></i>
                            <h6 class="mt-2">用户注册</h6>
                            <a href="#api-register" class="btn btn-sm btn-outline-primary">查看文档</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-box-arrow-in-right" style="font-size: 32px; color: #28a745;"></i>
                            <h6 class="mt-2">用户登录</h6>
                            <a href="#api-login" class="btn btn-sm btn-outline-success">查看文档</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-lightning" style="font-size: 32px; color: #ffc107;"></i>
                            <h6 class="mt-2">激活卡密</h6>
                            <a href="#api-activate" class="btn btn-sm btn-outline-warning">查看文档</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-shield-check" style="font-size: 32px; color: #17a2b8;"></i>
                            <h6 class="mt-2">验证卡密</h6>
                            <a href="#api-verify" class="btn btn-sm btn-outline-info">查看文档</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-heart-pulse" style="font-size: 32px; color: #dc3545;"></i>
                            <h6 class="mt-2">心跳检测</h6>
                            <a href="#api-heartbeat" class="btn btn-sm btn-outline-danger">查看文档</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-info-circle" style="font-size: 32px; color: #6c757d;"></i>
                            <h6 class="mt-2">获取信息</h6>
                            <a href="#api-info" class="btn btn-sm btn-outline-secondary">查看文档</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 用户注册 -->
    <div class="content-card mb-4" id="api-register">
        <div class="content-card-header">
            <h5><i class="bi bi-person-plus"></i> 用户注册</h5>
        </div>
        
        <div class="p-4">
            <div class="api-endpoint">
                <span class="method-badge method-post">POST</span>
                <code><?php echo SITE_URL; ?>/api.php?action=register</code>
            </div>
            
            <h6>请求参数</h6>
            <table class="table param-table">
                <thead>
                    <tr>
                        <th>参数名</th>
                        <th>类型</th>
                        <th>必填</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>action</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>操作类型，固定值：<code>register</code></td>
                    </tr>
                    <tr>
                        <td><code>token</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>API Key（项目令牌）</td>
                    </tr>
                    <tr>
                        <td><code>username</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>用户名（3-20字符）</td>
                    </tr>
                    <tr>
                        <td><code>password</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>密码</td>
                    </tr>
                    <tr>
                        <td><code>email</code></td>
                        <td>string</td>
                        <td><span class="badge-optional">可选</span></td>
                        <td>邮箱</td>
                    </tr>
                    <tr>
                        <td><code>phone</code></td>
                        <td>string</td>
                        <td><span class="badge-optional">可选</span></td>
                        <td>手机号</td>
                    </tr>
                    <tr>
                        <td><code>device_id</code></td>
                        <td>string</td>
                        <td><span class="badge-optional">可选</span></td>
                        <td>设备ID</td>
                    </tr>
                </tbody>
            </table>
            
            <h6 class="mt-4">代码示例</h6>
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#register-php">PHP</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#register-js">JavaScript</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#register-python">Python</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#register-curl">cURL</a>
                </li>
            </ul>
            
            <div class="tab-content">
                <div class="tab-pane fade show active" id="register-php">
                    <div class="code-block">&lt;?php
$apiUrl = '<?php echo SITE_URL; ?>/api.php';
$data = [
    'action' => 'register',
    'token' => 'your_api_token_here',
    'username' => 'testuser',
    'password' => 'password123',
    'email' => 'test@example.com',
    'device_id' => 'device_12345'
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);
?&gt;</div>
                </div>
                
                <div class="tab-pane fade" id="register-js">
                    <div class="code-block">fetch('<?php echo SITE_URL; ?>/api.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        action: 'register',
        token: 'your_api_token_here',
        username: 'testuser',
        password: 'password123',
        email: 'test@example.com',
        device_id: 'device_12345'
    })
})
.then(response => response.json())
.then(data => {
    console.log('注册结果:', data);
})
.catch(error => {
    console.error('错误:', error);
});</div>
                </div>
                
                <div class="tab-pane fade" id="register-python">
                    <div class="code-block">import requests
import json

url = '<?php echo SITE_URL; ?>/api.php'
data = {
    'action': 'register',
    'project_id': 1,
    'username': 'testuser',
    'password': 'password123',
    'email': 'test@example.com',
    'device_id': 'device_12345'
}

response = requests.post(url, json=data)
result = response.json()
print(result)</div>
                </div>
                
                <div class="tab-pane fade" id="register-curl">
                    <div class="code-block">curl -X POST '<?php echo SITE_URL; ?>/api.php' \
-H 'Content-Type: application/json' \
-d '{
    "action": "register",
    "project_id": 1,
    "username": "testuser",
    "password": "password123",
    "email": "test@example.com",
    "device_id": "device_12345"
}'</div>
                </div>
            </div>
            
            <h6 class="mt-4">响应示例</h6>
            <div class="response-example">
                <strong>成功响应：</strong>
                <div class="code-block">{
    "success": true,
    "message": "注册成功",
    "data": {
        "user_id": 1,
        "username": "testuser",
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_expire": "2025-11-02 12:00:00"
    }
}</div>
                
                <strong>失败响应：</strong>
                <div class="code-block">{
    "success": false,
    "message": "用户名已存在"
}</div>
            </div>
        </div>
    </div>
    
    <!-- 用户登录 -->
    <div class="content-card mb-4" id="api-login">
        <div class="content-card-header">
            <h5><i class="bi bi-box-arrow-in-right"></i> 用户登录</h5>
        </div>
        
        <div class="p-4">
            <div class="api-endpoint">
                <span class="method-badge method-post">POST</span>
                <code><?php echo SITE_URL; ?>/api.php?action=login</code>
            </div>
            
            <h6>请求参数</h6>
            <table class="table param-table">
                <thead>
                    <tr>
                        <th>参数名</th>
                        <th>类型</th>
                        <th>必填</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>action</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>操作类型，固定值：<code>login</code></td>
                    </tr>
                    <tr>
                        <td><code>project_id</code></td>
                        <td>integer</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>项目ID</td>
                    </tr>
                    <tr>
                        <td><code>username</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>用户名</td>
                    </tr>
                    <tr>
                        <td><code>password</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>密码</td>
                    </tr>
                    <tr>
                        <td><code>device_id</code></td>
                        <td>string</td>
                        <td><span class="badge-optional">可选</span></td>
                        <td>设备ID</td>
                    </tr>
                </tbody>
            </table>
            
            <h6 class="mt-4">响应示例</h6>
            <div class="response-example">
                <strong>成功响应：</strong>
                <div class="code-block">{
    "success": true,
    "message": "登录成功",
    "data": {
        "user_id": 1,
        "username": "testuser",
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "vip_level": 1,
        "balance": 100.00
    }
}</div>
            </div>
        </div>
    </div>
    
    <!-- 激活卡密 -->
    <div class="content-card mb-4" id="api-activate">
        <div class="content-card-header">
            <h5><i class="bi bi-lightning"></i> 激活卡密</h5>
        </div>
        
        <div class="p-4">
            <div class="api-endpoint">
                <span class="method-badge method-post">POST</span>
                <code><?php echo SITE_URL; ?>/api.php?action=activate</code>
            </div>
            
            <h6>请求参数</h6>
            <table class="table param-table">
                <thead>
                    <tr>
                        <th>参数名</th>
                        <th>类型</th>
                        <th>必填</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>action</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>操作类型，固定值：<code>activate</code></td>
                    </tr>
                    <tr>
                        <td><code>card_key</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>卡密</td>
                    </tr>
                    <tr>
                        <td><code>username</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>用户名</td>
                    </tr>
                    <tr>
                        <td><code>device_model</code></td>
                        <td>string</td>
                        <td><span class="badge-optional">可选</span></td>
                        <td>设备型号</td>
                    </tr>
                    <tr>
                        <td><code>device_info</code></td>
                        <td>object</td>
                        <td><span class="badge-optional">可选</span></td>
                        <td>设备详细信息（JSON）</td>
                    </tr>
                </tbody>
            </table>
            
            <h6 class="mt-4">代码示例</h6>
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#activate-php">PHP</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#activate-js">JavaScript</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#activate-java">Java</a>
                </li>
            </ul>
            
            <div class="tab-content">
                <div class="tab-pane fade show active" id="activate-php">
                    <div class="code-block">&lt;?php
$apiUrl = '<?php echo SITE_URL; ?>/api.php';
$data = [
    'action' => 'activate',
    'card_key' => 'KM-XXXX-XXXX-XXXX-XXXX',
    'username' => 'testuser',
    'device_model' => 'Xiaomi 13'
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);
?&gt;</div>
                </div>
                
                <div class="tab-pane fade" id="activate-js">
                    <div class="code-block">// 使用 Fetch API
fetch('<?php echo SITE_URL; ?>/api.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        action: 'activate',
        card_key: 'KM-XXXX-XXXX-XXXX-XXXX',
        username: 'testuser',
        device_model: 'Xiaomi 13'
    })
})
.then(res => res.json())
.then(data => console.log(data));</div>
                </div>
                
                <div class="tab-pane fade" id="activate-java">
                    <div class="code-block">// 使用 OkHttp
OkHttpClient client = new OkHttpClient();
MediaType JSON = MediaType.parse("application/json; charset=utf-8");

JSONObject json = new JSONObject();
json.put("action", "activate");
json.put("card_key", "KM-XXXX-XXXX-XXXX-XXXX");
json.put("username", "testuser");
json.put("device_model", "Xiaomi 13");

RequestBody body = RequestBody.create(JSON, json.toString());
Request request = new Request.Builder()
    .url("<?php echo SITE_URL; ?>/api.php")
    .post(body)
    .build();

Response response = client.newCall(request).execute();
String responseData = response.body().string();</div>
                </div>
            </div>
            
            <h6 class="mt-4">响应示例</h6>
            <div class="response-example">
                <strong>成功响应：</strong>
                <div class="code-block">{
    "success": true,
    "message": "激活成功",
    "data": {
        "card_key": "KM-XXXX-XXXX-XXXX-XXXX",
        "expire_time": "2025-11-02 12:00:00",
        "card_type": "time",
        "status": 1
    }
}</div>
                
                <strong>失败响应：</strong>
                <div class="code-block">{
    "success": false,
    "message": "卡密不存在或已被使用"
}</div>
            </div>
        </div>
    </div>
    
    <!-- 验证卡密 -->
    <div class="content-card mb-4" id="api-verify">
        <div class="content-card-header">
            <h5><i class="bi bi-shield-check"></i> 验证卡密</h5>
        </div>
        
        <div class="p-4">
            <div class="api-endpoint">
                <span class="method-badge method-get">GET</span>
                <span class="method-badge method-post">POST</span>
                <code><?php echo SITE_URL; ?>/api.php?action=verify</code>
            </div>
            
            <p class="text-muted">用于验证卡密是否有效、是否过期、用户是否匹配等。</p>
            
            <h6>请求参数</h6>
            <table class="table param-table">
                <thead>
                    <tr>
                        <th>参数名</th>
                        <th>类型</th>
                        <th>必填</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>action</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>操作类型，固定值：<code>verify</code></td>
                    </tr>
                    <tr>
                        <td><code>card_key</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>卡密</td>
                    </tr>
                    <tr>
                        <td><code>username</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>用户名</td>
                    </tr>
                </tbody>
            </table>
            
            <h6 class="mt-4">响应示例</h6>
            <div class="response-example">
                <strong>成功响应：</strong>
                <div class="code-block">{
    "success": true,
    "message": "验证成功",
    "data": {
        "card_key": "KM-XXXX-XXXX-XXXX-XXXX",
        "status": 1,
        "expire_time": "2025-11-02 12:00:00",
        "remaining_days": 30,
        "card_type": "time"
    }
}</div>
            </div>
        </div>
    </div>
    
    <!-- 心跳检测 -->
    <div class="content-card mb-4" id="api-heartbeat">
        <div class="content-card-header">
            <h5><i class="bi bi-heart-pulse"></i> 心跳检测</h5>
        </div>
        
        <div class="p-4">
            <div class="api-endpoint">
                <span class="method-badge method-post">POST</span>
                <code><?php echo SITE_URL; ?>/api.php?action=heartbeat</code>
            </div>
            
            <p class="text-muted">用于保持会话活跃，更新用户最后活跃时间。建议每5-10分钟调用一次。</p>
            
            <h6>请求参数</h6>
            <table class="table param-table">
                <thead>
                    <tr>
                        <th>参数名</th>
                        <th>类型</th>
                        <th>必填</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>action</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>操作类型，固定值：<code>heartbeat</code></td>
                    </tr>
                    <tr>
                        <td><code>card_key</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>卡密</td>
                    </tr>
                    <tr>
                        <td><code>username</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>用户名</td>
                    </tr>
                </tbody>
            </table>
            
            <h6 class="mt-4">响应示例</h6>
            <div class="response-example">
                <strong>成功响应：</strong>
                <div class="code-block">{
    "success": true,
    "message": "心跳成功",
    "data": {
        "timestamp": 1698912000,
        "server_time": "2025-10-02 12:00:00"
    }
}</div>
            </div>
        </div>
    </div>
    
    <!-- 获取卡密信息 -->
    <div class="content-card mb-4" id="api-info">
        <div class="content-card-header">
            <h5><i class="bi bi-info-circle"></i> 获取卡密信息</h5>
        </div>
        
        <div class="p-4">
            <div class="api-endpoint">
                <span class="method-badge method-get">GET</span>
                <code><?php echo SITE_URL; ?>/api.php?action=get_card_info</code>
            </div>
            
            <h6>请求参数</h6>
            <table class="table param-table">
                <thead>
                    <tr>
                        <th>参数名</th>
                        <th>类型</th>
                        <th>必填</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>action</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>操作类型，固定值：<code>get_card_info</code></td>
                    </tr>
                    <tr>
                        <td><code>card_key</code></td>
                        <td>string</td>
                        <td><span class="badge-required">必填</span></td>
                        <td>卡密</td>
                    </tr>
                </tbody>
            </table>
            
            <h6 class="mt-4">响应示例</h6>
            <div class="response-example">
                <strong>成功响应：</strong>
                <div class="code-block">{
    "success": true,
    "message": "获取成功",
    "data": {
        "card_key": "KM-XXXX-XXXX-XXXX-XXXX",
        "card_type": "time",
        "duration": 30,
        "status": 1,
        "device_id": "device_12345",
        "activated_at": "2025-10-02 12:00:00",
        "expire_date": "2025-11-02 12:00:00"
    }
}</div>
            </div>
        </div>
    </div>
    
    <!-- 错误代码 -->
    <div class="content-card mb-4">
        <div class="content-card-header">
            <h5><i class="bi bi-exclamation-triangle"></i> 错误代码说明</h5>
        </div>
        
        <div class="p-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>错误信息</th>
                        <th>说明</th>
                        <th>解决方案</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>卡密不存在</code></td>
                        <td>输入的卡密在系统中不存在</td>
                        <td>检查卡密是否正确，或联系管理员</td>
                    </tr>
                    <tr>
                        <td><code>卡密已被使用</code></td>
                        <td>该卡密已被其他设备激活</td>
                        <td>使用新的卡密，或联系管理员解绑</td>
                    </tr>
                    <tr>
                        <td><code>卡密已过期</code></td>
                        <td>卡密已超过有效期</td>
                        <td>购买新的卡密</td>
                    </tr>
                    <tr>
                        <td><code>用户不匹配</code></td>
                        <td>当前用户与绑定用户不一致</td>
                        <td>使用绑定的用户账号，或联系管理员</td>
                    </tr>
                    <tr>
                        <td><code>项目已禁用</code></td>
                        <td>该项目已被管理员禁用</td>
                        <td>联系管理员启用项目</td>
                    </tr>
                    <tr>
                        <td><code>参数错误</code></td>
                        <td>必填参数缺失或格式错误</td>
                        <td>检查请求参数是否完整和正确</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 最佳实践 -->
    <div class="content-card">
        <div class="content-card-header">
            <h5><i class="bi bi-lightbulb"></i> 最佳实践</h5>
        </div>
        
        <div class="p-4">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="bi bi-check-circle text-success"></i> 推荐做法</h6>
                    <ul>
                        <li>使用HTTPS加密传输数据</li>
                        <li>妥善保管API Key，不要泄露</li>
                        <li>实现请求重试机制（建议3次）</li>
                        <li>使用心跳接口保持会话活跃</li>
                        <li>缓存验证结果，避免频繁请求</li>
                        <li>记录API调用日志，便于排查问题</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6><i class="bi bi-x-circle text-danger"></i> 避免事项</h6>
                    <ul>
                        <li>不要在客户端硬编码API Key</li>
                        <li>不要频繁调用API（建议间隔>1秒）</li>
                        <li>不要明文传输敏感数据</li>
                        <li>不要忽略错误响应</li>
                        <li>不要在公开场合分享令牌</li>
                        <li>不要使用GET传输敏感参数</li>
                    </ul>
                </div>
            </div>
            
            <div class="alert alert-warning mt-3">
                <i class="bi bi-shield-exclamation"></i> <strong>安全提示：</strong>
                所有API调用应该在服务端进行，避免在客户端暴露令牌。如果必须在客户端调用，请使用加密和混淆技术保护令牌。
            </div>
        </div>
    </div>
</div>

<script>
// 平滑滚动到锚点
document.querySelectorAll('a[href^="#api-"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>
