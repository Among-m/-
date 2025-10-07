<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// 如果已登录，跳转到仪表板
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// 处理登录
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        $db = Database::getInstance();
        $admin = $db->fetchOne("SELECT * FROM admins WHERE username = ?", [$username]);
        
        if ($admin && password_verify($password, $admin['password'])) {
            if ($admin['status'] != 1) {
                $error = '账户已被禁用';
            } else {
                // 登录成功
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // 更新最后登录时间
                $db->update('admins', [
                    'last_login' => date('Y-m-d H:i:s')
                ], 'id = ?', [$admin['id']]);
                
                // 记录日志
                logAction('login', '管理员登录系统');
                
                // 设置cookie（记住我）
                if ($remember) {
                    setcookie('remember_user', $username, time() + 86400 * 30, '/');
                }
                
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = '用户名或密码错误';
        }
    }
}

$rememberedUser = $_COOKIE['remember_user'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Microsoft YaHei', Arial, sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 15px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 20px;
            border: 1px solid #e0e0e0;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .input-group-text {
            background: white;
            border-radius: 10px 0 0 10px;
            border: 1px solid #e0e0e0;
            border-right: none;
        }
        
        .input-group .form-control {
            border-radius: 0 10px 10px 0;
            border-left: none;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .features {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
        }
        
        .feature-item {
            display: inline-block;
            margin: 0 20px;
            font-size: 14px;
            color: #666;
        }
        
        .feature-item i {
            display: block;
            font-size: 24px;
            color: #667eea;
            margin-bottom: 8px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="bi bi-shield-lock"></i> <?php echo SITE_NAME; ?></h1>
                <p>高效管理项目卡密，安全对接APK应用</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo h($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo h($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">用户名</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" class="form-control" name="username" value="<?php echo h($rememberedUser); ?>" placeholder="请输入用户名" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">密码</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" class="form-control" name="password" placeholder="请输入密码" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 d-flex justify-content-between align-items-center">
    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember" <?php echo $rememberedUser ? 'checked' : ''; ?>>
        <label class="form-check-label" for="remember">
            记住我
        </label>
    </div>
    <a href="forgot_password.php" class="text-decoration-none">
        忘记密码?
    </a>
</div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right"></i> 登录系统
                    </button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="register.php" class="text-decoration-none">
                        <i class="bi bi-person-plus"></i> 还没有账号？立即注册
                    </a>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        超级管理员默认账号: admin / admin123456
                    </small>
                </div>
            </div>
            
            <div class="features">
                <div class="feature-item">
                    <i class="bi bi-shield-check"></i>
                    <div>安全可靠</div>
                </div>
                <div class="feature-item">
                    <i class="bi bi-gear"></i>
                    <div>功能全面</div>
                </div>
                <div class="feature-item">
                    <i class="bi bi-phone"></i>
                    <div>APK对接</div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <small class="text-white">
                © 2025 <?php echo SITE_NAME; ?>. All rights reserved.
            </small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



