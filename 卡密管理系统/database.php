<?php
// 数据库操作类
require_once 'config.php';

class Database {
    private $db;
    private static $instance = null;
    
    private function __construct() {
        try {
            $this->db = new PDO('sqlite:' . DB_FILE);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initDatabase();
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    // 初始化数据库表结构
    private function initDatabase() {
        $sql = "
        -- 管理员表
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            email TEXT,
            role TEXT DEFAULT 'admin',
            status INTEGER DEFAULT 1,
            last_login TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            updated_at TEXT DEFAULT (datetime('now', 'localtime'))
        );
        
        -- 项目表
        CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER DEFAULT 1,
            name TEXT NOT NULL,
            description TEXT,
            app_package TEXT,
            status INTEGER DEFAULT 1,
            total_cards INTEGER DEFAULT 0,
            used_cards INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            updated_at TEXT DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
        );
        
        -- 卡密表
        CREATE TABLE IF NOT EXISTS cards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            card_key TEXT UNIQUE NOT NULL,
            card_type TEXT DEFAULT 'time',
            duration INTEGER DEFAULT 0,
            status INTEGER DEFAULT 0,
            device_id TEXT,
            device_model TEXT,
            use_count INTEGER DEFAULT 0,
            max_use_count INTEGER DEFAULT 1,
            expire_date TEXT,
            activated_at TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            updated_at TEXT DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        );
        
        -- 用户表（使用卡密的终端用户）
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            card_key TEXT NOT NULL,
            device_id TEXT NOT NULL,
            device_model TEXT,
            device_info TEXT,
            expire_time TEXT,
            status INTEGER DEFAULT 1,
            last_active TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            updated_at TEXT DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (card_key) REFERENCES cards(card_key) ON DELETE CASCADE,
            UNIQUE(project_id, device_id)
        );
        
        -- APP用户表（注册登录系统）
        CREATE TABLE IF NOT EXISTS app_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER DEFAULT 0,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            email TEXT UNIQUE,
            phone TEXT UNIQUE,
            avatar TEXT,
            device_id TEXT,
            device_model TEXT,
            token TEXT UNIQUE,
            token_expire TEXT,
            vip_level INTEGER DEFAULT 0,
            balance REAL DEFAULT 0.00,
            status INTEGER DEFAULT 1,
            last_login TEXT,
            login_count INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            updated_at TEXT DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        );
        
        -- API Key表
        CREATE TABLE IF NOT EXISTS api_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            token TEXT UNIQUE NOT NULL,
            name TEXT,
            status INTEGER DEFAULT 1,
            last_used TEXT,
            expire_at TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        );
        
        -- 操作日志表
        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER,
            project_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        );
        
        -- 卡密使用记录表
        CREATE TABLE IF NOT EXISTS card_usage_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            card_key TEXT NOT NULL,
            project_id INTEGER NOT NULL,
            device_id TEXT,
            action TEXT NOT NULL,
            ip_address TEXT,
            result TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (card_key) REFERENCES cards(card_key) ON DELETE CASCADE,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        );
        
        -- 创建索引
        CREATE INDEX IF NOT EXISTS idx_cards_project ON cards(project_id);
        CREATE INDEX IF NOT EXISTS idx_cards_key ON cards(card_key);
        CREATE INDEX IF NOT EXISTS idx_cards_status ON cards(status);
        CREATE INDEX IF NOT EXISTS idx_users_project ON users(project_id);
        CREATE INDEX IF NOT EXISTS idx_users_device ON users(device_id);
        CREATE INDEX IF NOT EXISTS idx_logs_admin ON logs(admin_id);
        CREATE INDEX IF NOT EXISTS idx_logs_project ON logs(project_id);
        CREATE INDEX IF NOT EXISTS idx_card_usage_card ON card_usage_logs(card_key);
        CREATE INDEX IF NOT EXISTS idx_api_tokens_project ON api_tokens(project_id);
        ";
        
        $this->db->exec($sql);
        
        // 创建默认管理员账户（如果不存在）
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM admins");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $defaultPassword = password_hash('admin123456', PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("INSERT INTO admins (username, password, email, role) VALUES (?, ?, ?, ?)");
            $stmt->execute(['admin', $defaultPassword, 'admin@example.com', 'super_admin']);
        }
    }
    
    // 执行查询
    public function query($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('数据库查询错误: ' . $e->getMessage());
            return false;
        }
    }
    
    // 获取单行数据
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }
    
    // 获取多行数据
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }
    
    // 插入数据
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->query($sql, $values);
        return $stmt ? $this->db->lastInsertId() : false;
    }
    
    // 更新数据
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "{$field} = ?";
            $values[] = $value;
        }
        
        $values = array_merge($values, $whereParams);
        
        $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$where}";
        
        return $this->query($sql, $values) !== false;
    }
    
    // 删除数据
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $whereParams) !== false;
    }
    
    // 获取记录数
    public function count($table, $where = '1=1', $whereParams = []) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $whereParams);
        return $stmt ? $stmt->fetchColumn() : 0;
    }
    
    // 开始事务
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    // 提交事务
    public function commit() {
        return $this->db->commit();
    }
    
    // 回滚事务
    public function rollBack() {
        return $this->db->rollBack();
    }
}
?>

