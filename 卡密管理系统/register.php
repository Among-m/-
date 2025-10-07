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

// 处理注册
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    
    // 验证输入
    if (empty($username) || empty($password) || empty($confirmPassword)) {
        $error = '请填写所有必填项';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名长度必须在3-20个字符之间';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = '用户名只能包含字母、数字和下划线';
    } elseif (strlen($password) < 6) {
        $error = '密码长度不能少于6位';
    } elseif ($password !== $confirmPassword) {
        $error = '两次输入的密码不一致';
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } else {
        $db = Database::getInstance();
        
        // 检查用户名是否已存在
        $exists = $db->fetchOne("SELECT id FROM admins WHERE username = ?", [$username]);
        if ($exists) {
            $error = '用户名已被注册';
        } else {
            // 检查邮箱是否已被使用
            if ($email) {
                $emailExists = $db->fetchOne("SELECT id FROM admins WHERE email = ?", [$email]);
                if ($emailExists) {
                    $error = '邮箱已被注册';
                }
            }
            
            if (!$error) {
                // 创建管理员账号（默认角色为admin）
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                
                $data = [
                    'username' => $username,
                    'password' => $hashedPassword,
                    'email' => $email,
                    'role' => 'admin', // 默认为普通管理员
                    'status' => 1
                ];
                
                $adminId = $db->insert('admins', $data);
                
                if ($adminId) {
                    $success = '注册成功！3秒后自动跳转到登录页面...';
                    // 记录日志
                    $_SESSION['temp_admin_id'] = $adminId;
                    logAction('register', "新管理员注册: $username");
                    unset($_SESSION['temp_admin_id']);
                    
                    // 3秒后跳转到登录页面
                    header("refresh:3;url=index.php");
                } else {
                    $error = '注册失败，请稍后重试';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册管理员账号 - <?php echo SITE_NAME; ?></title>
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
        
        .register-container {
            width: 100%;
            max-width: 500px;
            padding: 15px;
        }
        
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .register-header p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }
        
        .register-body {
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
        
        .btn-register {
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
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-box i {
            color: #17a2b8;
            margin-right: 8px;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            background: #e0e0e0;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s;
            width: 0%;
        }
        
        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h1><i class="bi bi-person-plus-fill"></i> 注册管理员账号</h1>
                <p>创建账号后即可管理您的项目和用户</p>
            </div>
            
            <div class="register-body">
                <div class="info-box">
                    <i class="bi bi-info-circle-fill"></i>
                    <small>注册后您将获得<strong>普通管理员</strong>权限，可以创建项目、生成卡密、管理用户等。数据完全隔离，互不影响。</small>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo h($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo h($success); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm">
                    <div class="mb-3">
                        <label class="form-label">用户名 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" class="form-control" name="username" id="username" 
                                   placeholder="3-20个字符，字母数字下划线" 
                                   pattern="[a-zA-Z0-9_]{3,20}" 
                                   required autofocus
                                   value="<?php echo h($_POST['username'] ?? ''); ?>">
                        </div>
                        <small class="text-muted">用户名将作为您的登录账号</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">邮箱</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" class="form-control" name="email" 
                                   placeholder="用于找回密码（可选）"
                                   value="<?php echo h($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">密码 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" class="form-control" name="password" id="password" 
                                   placeholder="至少6位" 
                                   minlength="6"
                                   required
                                   oninput="checkPasswordStrength()">
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <small class="text-muted" id="strengthText">请输入密码</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">确认密码 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password"
                                   placeholder="再次输入密码" 
                                   minlength="6"
                                   required>
                        </div>
                        <small class="text-danger" id="passwordMatch" style="display:none;">两次密码不一致</small>
                    </div>
                    
                    <button type="submit" class="btn btn-register" id="submitBtn">
                        <i class="bi bi-check-lg"></i> 立即注册
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> 已有账号? 返回登录
                    </a>
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
    <script>
        // 检查密码强度
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength-bar';
                strengthText.textContent = '请输入密码';
                strengthText.className = 'text-muted';
                return;
            }
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;
            
            if (strength <= 2) {
                strengthBar.className = 'password-strength-bar strength-weak';
                strengthText.textContent = '密码强度：弱';
                strengthText.className = 'text-danger';
            } else if (strength <= 3) {
                strengthBar.className = 'password-strength-bar strength-medium';
                strengthText.textContent = '密码强度：中';
                strengthText.className = 'text-warning';
            } else {
                strengthBar.className = 'password-strength-bar strength-strong';
                strengthText.textContent = '密码强度：强';
                strengthText.className = 'text-success';
            }
        }
        
        // 检查密码是否匹配
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchText = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirmPassword && password !== confirmPassword) {
                matchText.style.display = 'block';
                submitBtn.disabled = true;
            } else {
                matchText.style.display = 'none';
                submitBtn.disabled = false;
            }
        });
        
        // 表单提交验证
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('两次输入的密码不一致！');
                return false;
            }
        });
    </script>
</body>
</html>

