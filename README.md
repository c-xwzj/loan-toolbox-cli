# falconshop/loan-ops-commands

Toolbox 远程运维命令库。核心逻辑只维护一份，各国 `*-loan-api`（贷超）/ `*-core-admin`（信贷）/ `*-pay`（支付）通过 Composer 安装薄命令入口，由 toolbox SSH 远程调用。

## 模块

| 模块 | 命令 | 适用项目 |
|------|------|----------|
| Admin | `admin:account-sync` | 创建/完整更新后台账号（密码 hash 原样写入） |
| Admin | `admin:account-status` | 设置账号启用状态（`enabled` 0=关闭 1=开启） |
| Admin | `admin:password-reset` | 仅更新密码 hash，不改权限组/用户名 |
| AppConfig | `app:config-sync` | 贷超 `app_config` 增删改（toolbox 远程调用） |
| Credit | `credit:overdue-simulate` | 测试环境：把订单改成「已逾期 N 天」，App 利息与真实逾期一致 |
| Repay | `repay:manual-callback` | 还款回调补单（入队 `repay-callback-queue`） |

### PHP / 框架版本（安装前必读）

各国项目 **PHP 版本不一致**，本包 `composer.json` 约束为 **`php >= 7.4`**（取贷超最低版本）。共享业务代码（`src/`）须保持 **PHP 7.4 兼容**，不得使用 PHP 8 专有语法。

| 项目类型 | PHP | 框架 | 安装模块 | `commands.php` 注册 |
|----------|-----|------|----------|---------------------|
| `*-loan-api`（贷超） | **>= 7.4** | Hyperf **2.x** | Admin + AppConfig | `composer require` 即可（ConfigProvider 自动注册） |
| `*-core-admin`（信贷后台） | **>= 8.0** | Laravel | Admin | 无需（`LoanOpsServiceProvider` 自动发现） |
| `*-core-api`（信贷核心） | **>= 8.0** | Hyperf **3.x** | Credit | `composer require` 即可（检测到 `IntoCollectionCommand` 后自动注册） |
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

# 更新
composer require falconshop/loan-toolbox-cli:dev-main --no-interaction
rm -rf runtime/container
php bin/hyperf.php | grep admin:account-enable
```



### 2.3 贷超 `*-loan-api`（Hyperf 2，PHP >= 7.4）

`composer require` 后包内 **Hyperf ConfigProvider** 会自动注册 Admin / AppConfig 命令，**无需**改 `config/autoload/commands.php`。

验证：

```bash
php bin/hyperf.php | grep -E 'admin:account|app:config-sync'
```

应看到：

```
admin:account-sync
admin:account-status
admin:password-reset
app:config-sync
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

# App 配置增删改（本地可直接传 JSON；toolbox 远程调用传 base64(JSON)，避免中文/特殊字符经 SSH 被改写）
php bin/hyperf.php app:config-sync '{"action":"create","app_id":1,"key":"shortUrl","value":"https://example.com","note":""}' -j
php bin/hyperf.php app:config-sync '{"action":"update","id":10,"app_id":1,"key":"shortUrl","value":"https://example.com/v2","note":"需上传身份证用户比例%"}' -j
php bin/hyperf.php app:config-sync '{"action":"delete","id":10}' -j
```

成功响应示例：

```json
{"success":true,"message":"创建成功","id":10}
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

### 信贷核心（core-api）逾期模拟

**给谁用：** 测试要验「订单逾期后 App 利息 / 结清金额」，不想等自然到期。

**一句话：** 把订单时间拨成「已经逾期 N 天」，再跑入催；App 看到的利息天数和真实逾期一样。

```bash
# 常用：逾期 1 天（南非时区）
php bin/hyperf.php credit:overdue-simulate {sn} --days=1 --timezone=Africa/Johannesburg -j

# 默认逾期 3 天
php bin/hyperf.php credit:overdue-simulate {sn} --timezone=Africa/Johannesburg -j

# 指定到期日（少用）
php bin/hyperf.php credit:overdue-simulate {sn} --due-date=2026-10-28 --timezone=Africa/Johannesburg -j
```

#### 一、怎么理解「逾期天数」和「利息天数」（容易混）

假设今天是 **10-29**，产品周期 **1 天**，命令 `--days=1`：

| 概念 | 是什么 | 本例 |
|------|--------|------|
| 逾期天数 `--days` | 相对「到期日」过了几天（状态/入催用） | 1 |
| 利息天数 | App 算利息：从**下单那天**数到**今天**（日历天） | **2** |

时间轴（工具会自动改库对齐）：

```
10-27 下单/起息  →  10-28 到期  →  10-29 今天（已逾期 1 天）
|<-------- 利息按 2 天算 -------->|
```

所以：**逾期 1 天 ≠ 只收 1 天利息**。1 天产品 + 逾期 1 天 → 详情里大约是 **2 天利息**（例如约 1.95，不是约 0.97）。

利息封顶、应还公式见信贷业务文档《产品与计费》，不在本命令说明里。

#### 跑完怎么验收

1. 命令成功：`success: true`，且出现入催日志（插入罚息 / 更新放款 / 更新还款计划）。
2. 看 JSON 关键字段：

| 字段 | 含义 | 本例应接近 |
|------|------|------------|
| `apply_time_text` | 下单/起息日（已拨回） | `2026-10-27 00:00:00` |
| `due_date_text` | 到期日 | `2026-10-28 23:59:59` |
| `expected_interest_days` | 今天看详情应有的利息天数 | `2` |
| `overdue_days` | 逾期天数 | `1` |
| `plan_status` | `3` = 已逾期 | `3` |

3. 打开 App / 后台订单详情，利息应接近「按 `expected_interest_days` 天」的金额。

成功示例：

```json
{
  "success": true,
  "message": "逾期模拟完成",
  "sn": "317932476342",
  "local_today": "2026-10-29",
  "apply_time_text": "2026-10-27 00:00:00",
  "due_date_text": "2026-10-28 23:59:59",
  "expected_interest_days": 2,
  "overdue_days": 1,
  "plan_status": 3,
  "into_ran": true
}
```

#### 注意

- 仅测试环境；会改该单的到期日、起息日、下单时间，再执行真实入催。
- 只改「已逾期」状态、不拨下单日 → App 利息仍会按「今天下单」只算约 1 天，和真实逾期不一致（本命令已避免）。
- 多期订单默认改第 1 期；其他期用 `--term=2` 等。

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

## 更新记录

| 日期 | 内容 |
|------|------|
| 2026-10-30 | `app:config-sync` 支持 base64(JSON) 入参，避免 SSH 链路剥中文导致 note 残缺 |
| 2026-10-29 | 新增 `app:config-sync`：贷超 `app_config` 增删改，供 toolbox SSH 调用 |
| 2026-10-29 | 逾期模拟：补充测试可读说明（逾期天数 vs 利息天数、验收字段、成功示例） |
