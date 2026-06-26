# falconshop/loan-toolbox-cli

Toolbox 远程运维命令库。核心逻辑只维护一份，各国 `*-loan-api`（贷超）/ `*-core-admin`（信贷）/ `*-pay`（支付）通过 Composer 安装薄命令入口，由 toolbox SSH 远程调用。

## 模块

| 模块 | 命令 | 适用项目 |
|------|------|----------|
| Admin | `admin:account-sync` | 同步后台账号（密码 hash 原样写入） |
| Admin | `admin:account-disable` | 关闭（禁用）后台账号 |
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

## 一、发布到 Git（维护者）

新增/修改 `src/` 共享逻辑时，须在 **PHP 7.4** 下可运行（贷超 `*-loan-api` 仍为此版本）。`hyperf/Repay/`、`laravel/` 适配层可按目标项目使用更高版本 API，但勿把 PHP 8 语法泄漏到 `src/`。

```bash
cd /path/to/loan-toolbox-cli
git init
git add .
git commit -m "feat: initial loan-toolbox-cli with admin sync/disable"
git remote add origin git@github.com:c-xwzj/loan-toolbox-cli.git
git push -u origin main
git tag v1.0.0
git push origin v1.0.0
```

---

## 二、安装（各国业务项目）

### 2.1 配置 Composer 私有源

在目标项目根目录的 `composer.json` 中增加 `repositories`（**只需配置一次**）：

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:c-xwzj/loan-toolbox-cli.git",
      "no-api": true
    }
  ]
}
```

> **私有仓库必加 `"no-api": true`**，让 Composer 直接 `git clone`（走 SSH），不走 GitHub API（未鉴权会 404）。

服务器配置 **SSH Deploy Key**（一次性），确认能拉代码：

```bash
ssh -T git@github.com
git ls-remote git@github.com:c-xwzj/loan-toolbox-cli.git
```

Deploy Key 配置步骤见下文「私有仓库 SSH 部署」。

### 2.2 私有仓库 SSH 部署（不用 Token）

在**服务器容器内**执行一次：

```bash
# 1. 生成密钥（一路回车）
ssh-keygen -t ed25519 -C "za-loan-api-deploy" -f ~/.ssh/id_ed25519 -N ""

# 2. 打印公钥，复制到 GitHub
cat ~/.ssh/id_ed25519.pub
```

GitHub 打开 `c-xwzj/loan-toolbox-cli` → **Settings** → **Deploy keys** → **Add deploy key** → 粘贴公钥 → 保存（只读即可）。

```bash
# 3. 验证
ssh -T git@github.com
git ls-remote git@github.com:c-xwzj/loan-toolbox-cli.git

# 4. 安装
cd /www/sites/za-loan-api
composer update falconshop/loan-toolbox-cli
rm -rf runtime/container
php bin/hyperf.php | grep admin:account
```

`composer.json` 保持：

```json
"repositories": [{
  "type": "vcs",
  "url": "git@github.com:c-xwzj/loan-toolbox-cli.git",
  "no-api": true
}]
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
admin:account-disable
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
admin:account-disable
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
php bin/hyperf.php admin:account-disable '{"account":"zhangsan"}' -j
```

### 信贷（core-admin）

```bash
# 同步账号
php artisan admin:account-sync '{"action":"create","username":"张三","account":"zhangsan","password":"$2y$10$...","group_id":5,"enabled":1}' --json

# 更新账号
php artisan admin:account-sync '{"action":"update","username":"张三","account":"zhangsan","password":"$2y$10$...","group_id":5,"enabled":1,"admin_id":123}' --json

# 关闭账号
php artisan admin:account-disable '{"account":"zhangsan"}' --json
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

---

## 四、与 toolbox 配合

toolbox 通过 SSH 在目标服务器执行上述命令，**不再直连写各国数据库**。

| 后台 | projects.name | 命令入口 |
|------|---------------|----------|
| 贷超 | `{country}-loan-api` | `php bin/hyperf.php ... -j` |
| 信贷 | `{country}-core-admin` | `php artisan ... --json` |
| 支付 | `{country}-pay` | `php bin/hyperf.php repay:manual-callback ... -j` |

示例：`ng-loan-api`、`bd-core-admin`、`ng-pay`（`env_type` 0=测试，1=生产）。

部署到服务器后，在 toolbox 后台「账号同步」「还款回调补单」页面即可使用；日志见 toolbox `storage/logs/admin_account_sync.log`、`payment_callback.log`。

---

## 五、升级

```bash
composer update falconshop/loan-toolbox-cli
```

建议维护者发版打 tag，各国项目锁定 `^1.0` 按需升级。

---

## 六、本地 path 开发（可选）

**服务器 / 测试环境请用第二节的 `vcs` 源**（`composer.json` 里配置 Git 地址）。`path` 仅本机联调，不要提交到各国项目仓库。

本机若与 `loan-toolbox-cli` 同目录，可临时覆盖为 path（不改已提交的 `composer.json`）：

```bash
composer config repositories.loan-toolbox-cli '{"type":"path","url":"../../loan-toolbox-cli","options":{"symlink":true}}'
composer update falconshop/loan-toolbox-cli
```

恢复为 Git 源：

```bash
composer config --unset repositories.loan-toolbox-cli
composer update falconshop/loan-toolbox-cli
```

---

## 七、目录结构

```
src/Admin/AdminAccountHandler.php       # 核心业务（一份）
src/Repay/RepayManualCallbackHandler.php
src/Contract/AdminRepositoryInterface.php
src/Contract/RepayRecordRepositoryInterface.php
hyperf/Admin/MarketAdminRepository.php  # 贷超 admin 表
hyperf/Repay/RepayRecordRepository.php  # 支付 repay_record 表
laravel/Admin/CoreAdminRepository.php   # 信贷 admin 表
hyperf/Command/                         # Hyperf 命令入口
laravel/Commands/                       # Artisan 命令入口
```

后续新功能在 `src/` 下新增模块目录即可，各国 `composer update` 升级。
