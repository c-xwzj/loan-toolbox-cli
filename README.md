# falconshop/loan-ops-commands

Toolbox 远程运维命令库。核心逻辑只维护一份，各国 `*-loan-api`（贷超）/ `*-core-admin`（信贷）/ `*-pay`（支付）通过 Composer 安装薄命令入口，由 toolbox SSH 远程调用。

## 模块

| 模块 | 命令 | 适用项目 |
|------|------|----------|
| Admin | `admin:account-sync` | 同步后台账号（密码 hash 原样写入） |
| Admin | `admin:account-status` | 设置账号启用状态（`enabled` 0=关闭 1=开启） |
| Repay | `repay:manual-callback` | 还款回调补单（入队 `repay-callback-queue`） |

### PHP / 框架版本（安装前必读）

各国项目 **PHP 版本不一致**，本包 `composer.json` 约束为 **`php >= 7.4`**（取贷超最低版本）。共享业务代码（`src/`）须保持 **PHP 7.4 兼容**，不得使用 PHP 8 专有语法。

| 项目类型 | PHP | 框架 | 安装模块 | `commands.php` 注册 |
|----------|-----|------|----------|---------------------|
| `*-loan-api`（贷超） | **>= 7.4** | Hyperf **2.x** | Admin | `composer require` 即可（ConfigProvider 自动注册） |
| `*-core-admin`（信贷） | **>= 8.0** | Laravel | Admin | 无需（`LoanOpsServiceProvider` 自动发现） |
| `*-pay`（支付） | **>= 8.0** | Hyperf **3.x** | Repay | `composer require` 即可（检测到 `RepayCallbackQueue` 后自动注册） |

> **注意**：`repay:manual-callback` 仅部署在 `*-pay`（PHP 8 + Hyperf 3），**不要**装到 `*-loan-api`（PHP 7.4 + Hyperf 2）。贷超项目只注册 Admin 命令即可。

---

> 将 `YOUR_GIT_HOST` 换成实际 Git 地址（GitHub / GitLab / Gitee 等）。

---

## 二、安装（各国业务项目）

### 安装命令

在项目根目录执行：

```bash
# 1. 先告诉 Composer 去哪找这个包
composer config repositories.loan-toolbox-cli vcs https://github.com/c-xwzj/loan-toolbox-cli.git

# 2. 再安装
composer require falconshop/loan-toolbox-cli:dev-main --no-interaction

# 3. 验证
composer show falconshop/loan-toolbox-cli
ls vendor/falconshop/loan-toolbox-cli
rm -rf runtime/container
php bin/hyperf.php | grep admin:account    安装步骤写到  loan-toolbox-cli 文件下 完善整理文档
```

### 2.3 贷超 `*-loan-api`（Hyperf 2，PHP >= 7.4）

`composer require` 后包内 **Hyperf ConfigProvider** 会自动注册 Admin 命令，**无需**改 `config/autoload/commands.php`。

验证：

```bash
php bin/hyperf.php | grep admin:account
```

应看到：

```
admin:account-sync
admin:account-status
```

> 若项目曾在 `commands.php` 手动注册过上述命令，可删除重复项；保留空数组 `return [];` 即可。

### 2.4 信贷 `*-core-admin`（Laravel，PHP >= 8.0）额外配置

`composer require` 后 Laravel 会通过包内 `LoanOpsServiceProvider` **自动发现**命令，一般无需改 `config/app.php`。

若项目关闭了 package discover，手动在 `config/app.php` 的 `providers` 添加：

```php
Falconshop\LoanOps\Laravel\LoanOpsServiceProvider::class,
```

验证：

```bash
php artisan list | grep admin:account
```

应看到：

```
admin:account-sync
admin:account-status
```

### 2.5 支付 `*-pay`（Hyperf 3，PHP >= 8.0）

`composer require` 后自动注册 `repay:manual-callback`（检测到宿主项目存在 `App\Queue\RepayCallbackQueue` 时）。**无需**改 `commands.php`。

验证：

```bash
php bin/hyperf.php | grep repay:manual-callback
```

应看到：

```
repay:manual-callback
```

---

## 三、命令用法

### 贷超（loan-api）

```bash
# 同步账号
php bin/hyperf.php admin:account-sync '{"action":"create","username":"张三","account":"zhangsan","password":"$2y$10$...","group_id":5,"enabled":1}' -j

# 更新账号
php bin/hyperf.php admin:account-sync '{"action":"update","username":"张三","account":"zhangsan","password":"$2y$10$...","group_id":5,"enabled":1,"admin_id":123}' -j

# 关闭账号
php bin/hyperf.php admin:account-status '{"account":"zhangsan","enabled":0}' -j
php bin/hyperf.php admin:account-status '{"account":"zhangsan","enabled":1}' -j
```

### 信贷（core-admin）

```bash
# 同步账号
php artisan admin:account-sync '{"action":"create","username":"张三","account":"zhangsan","password":"$2y$10$...","group_id":5,"enabled":1}' --json

# 更新账号
php artisan admin:account-sync '{"action":"update","username":"张三","account":"zhangsan","password":"$2y$10$...","group_id":5,"enabled":1,"admin_id":123}' --json

# 关闭账号
php artisan admin:account-status '{"account":"zhangsan","enabled":0}' --json
php artisan admin:account-status '{"account":"zhangsan","enabled":1}' --json
```

### 支付（pay）

```bash
# 手动还款回调补单（repay_id、金额，可选第三方流水号）
php bin/hyperf.php repay:manual-callback <repay_id> <amount> [txn] -j
```

示例：

```bash
php bin/hyperf.php repay:manual-callback 1234567890 50000 -j
php bin/hyperf.php repay:manual-callback 1234567890 50000 TXN-20260101 -j
```

成功响应示例：

```json
{"success":true,"message":"入队成功","repay_id":1234567890,"amount":50000,"txn":"MANUAL-1735689600","queue":"repay-callback-queue"}
```

### JSON 响应格式

成功示例：

```json
{"success":true,"message":"账号已创建","admin_id":123}
```

失败示例：

```json
{"success":false,"message":"权限组不存在或已禁用 id=99"}
```
