# 多租户/裂变功能升级指南

## 🎉 新功能说明

本次升级实现了**多管理员独立管理**和**裂变功能**，每个管理员只能看到和管理自己创建的项目及项目下的用户。

### 核心改进

1. **项目归属管理员**
   - 项目表添加 `admin_id` 字段
   - 每个项目归属于创建它的管理员
   - 管理员只能管理自己创建的项目

2. **用户归属项目**
   - 用户表添加 `project_id` 字段
   - 用户注册时必须指定项目ID
   - 管理员只能看到自己项目下的用户

3. **数据隔离**
   - 不同管理员的数据完全隔离
   - 实现真正的多租户架构
   - 支持裂变和分销模式

## 📊 数据库变更

### 1. projects 表
```sql
ALTER TABLE projects ADD COLUMN admin_id INTEGER DEFAULT 1;
ALTER TABLE projects ADD FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE;
```

### 2. app_users 表
```sql
ALTER TABLE app_users ADD COLUMN project_id INTEGER DEFAULT 0;
ALTER TABLE app_users ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE;
```

## 🔧 升级步骤

### 重要提示
由于SQLite不支持ALTER TABLE ADD CONSTRAINT，最简单的方法是：

**删除旧数据库重新初始化**（推荐）

1. 找到数据库文件（通常是 `data.db`）
2. 删除该文件
3. 重新访问管理后台，系统会自动创建新表结构

### 如果需要保留数据

如果你已经有测试数据需要保留，可以手动执行以下步骤：

1. **备份当前数据库**
```bash
cp data.db data.db.backup
```

2. **创建新表结构**（已完成，在database.php中）

3. **迁移数据**
```sql
-- 更新现有项目，分配给管理员（假设admin_id=1）
UPDATE projects SET admin_id = 1 WHERE admin_id IS NULL OR admin_id = 0;

-- 更新现有用户，分配到第一个项目（假设project_id=1）
UPDATE app_users SET project_id = 1 WHERE project_id IS NULL OR project_id = 0;
```

## 📱 API 变更

### 用户注册接口

**旧版本**（已废弃）：
```json
{
  "action": "register",
  "username": "testuser",
  "password": "123456",
  "email": "test@qq.com"
}
```

**新版本**（必须使用）：
```json
{
  "action": "register",
  "project_id": 1,        // 新增：必填参数
  "username": "testuser",
  "password": "123456",
  "email": "test@qq.com",
  "phone": "13800138000",
  "device_id": "设备ID",
  "device_model": "Xiaomi 13"
}
```

### 如何获取 project_id

1. 登录管理后台
2. 进入"项目管理"
3. 查看或创建项目
4. 每个项目都有唯一的ID，在APP中使用这个ID注册用户

## 🎯 使用场景

### 场景1：单管理员多项目
- 一个管理员创建多个项目（如：Android版、iOS版）
- 不同项目的用户独立管理
- 适合一个公司管理多个APP

### 场景2：多管理员裂变
- 主管理员创建子管理员账号
- 每个子管理员创建自己的项目
- 子管理员只能看到自己的用户
- 适合代理分销模式

### 场景3：多租户SaaS
- 每个租户（公司/团队）有独立账号
- 租户之间数据完全隔离
- 适合SaaS平台

## 🔐 权限说明

### 管理员权限
- ✅ 只能看到自己创建的项目
- ✅ 只能管理自己项目下的用户
- ✅ 只能管理自己项目下的卡密
- ❌ 无法访问其他管理员的数据

### 用户归属
- 用户创建时必须指定项目ID
- 用户永久归属于该项目
- 用户数据只有项目创建者可见

## 🧪 测试指南

### 1. 创建多个管理员

```sql
-- 创建第二个管理员
INSERT INTO admins (username, password, email, role) 
VALUES ('admin2', '$2y$10$...', 'admin2@example.com', 'admin');
```

### 2. 测试数据隔离

1. 用admin登录，创建项目A
2. 用admin2登录，创建项目B
3. 在项目A中注册用户
4. 在项目B中注册用户
5. 验证：admin只能看到项目A的用户
6. 验证：admin2只能看到项目B的用户

### 3. 测试API注册

使用Postman测试：

**为项目1注册用户：**
```json
POST /api.php
{
  "action": "register",
  "project_id": 1,
  "username": "user_project1",
  "password": "123456",
  "device_id": "device001"
}
```

**为项目2注册用户：**
```json
POST /api.php
{
  "action": "register",
  "project_id": 2,
  "username": "user_project2",
  "password": "123456",
  "device_id": "device002"
}
```

## 📝 注意事项

1. **project_id 是必填参数**
   - API注册时必须提供
   - 后台添加用户时必须选择项目

2. **数据完全隔离**
   - 不同管理员之间完全看不到对方数据
   - 无法跨项目查看用户

3. **删除级联**
   - 删除项目会删除该项目下所有用户
   - 删除管理员会删除该管理员的所有项目和用户
   - 请谨慎操作

4. **首次升级**
   - 推荐删除旧数据库重新开始
   - 如果已有重要数据，请先备份

## 🎨 界面变化

### 用户管理页面
- 新增"项目"列，显示用户所属项目
- 添加用户时需要选择项目
- 搜索和筛选功能保持不变

### 项目管理页面
- 显示项目归属的管理员
- 只能看到自己创建的项目

## 💡 最佳实践

### 1. 项目命名规范
```
项目名称格式：公司名-APP名-平台
示例：
- 张三公司-计步APP-Android
- 张三公司-计步APP-iOS
- 李四公司-阅读APP-Android
```

### 2. 用户组织
- 一个APP一个项目
- 不同版本可以共用一个项目
- 或者每个版本独立项目（推荐）

### 3. 管理员分配
- 主管理员：创建和管理所有代理
- 代理管理员：管理自己的客户
- 客户管理员：管理自己的用户

## 🐛 常见问题

### Q1: 升级后看不到用户？
A: 因为旧用户没有project_id，需要手动分配：
```sql
UPDATE app_users SET project_id = 1 WHERE project_id = 0 OR project_id IS NULL;
```

### Q2: 如何查看某个项目ID？
A: 进入项目管理页面，项目ID就在列表的第一列

### Q3: 可以转移用户到其他项目吗？
A: 可以，在数据库中修改用户的project_id

### Q4: 如何创建子管理员？
A: 在"管理员"页面添加新管理员账号，他们登录后可以创建自己的项目

## 📞 技术支持

如有问题，请提供：
1. 错误信息截图
2. 操作步骤
3. 数据库版本
4. 是否已按升级步骤操作

---

升级完成后，您将拥有一个完整的多租户卡密管理系统！🎉

