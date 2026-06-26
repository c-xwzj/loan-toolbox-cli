<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Admin;

use Falconshop\LoanOps\Contract\AdminRepositoryInterface;
use Falconshop\LoanOps\Exception\OpsCommandException;
use Falconshop\LoanOps\Support\JsonCommandResult;

class AdminAccountHandler
{
    /** @var AdminRepositoryInterface */
    private $repository;

    public function __construct(AdminRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{exit_code: int, line: string, payload: array<string, mixed>}
     */
    public function sync(array $payload): array
    {
        try {
            $action = (string) ($payload['action'] ?? '');
            if (!in_array($action, ['create', 'update'], true)) {
                throw new OpsCommandException('action 必须为 create 或 update');
            }

            $account = trim((string) ($payload['account'] ?? ''));
            if ($account === '') {
                throw new OpsCommandException('account 不能为空');
            }

            $username = trim((string) ($payload['username'] ?? ''));
            if ($username === '') {
                throw new OpsCommandException('username 不能为空');
            }

            $password = (string) ($payload['password'] ?? '');
            if ($password === '') {
                throw new OpsCommandException('password 不能为空');
            }

            $groupId = (int) ($payload['group_id'] ?? 0);
            if ($groupId <= 0) {
                throw new OpsCommandException('group_id 无效');
            }

            if (!$this->repository->groupExists($groupId)) {
                throw new OpsCommandException("权限组不存在或已禁用 id={$groupId}");
            }

            $enabled = (int) ($payload['enabled'] ?? 1);
            $data = [
                'username' => $username,
                'account' => $account,
                'password' => $password,
                'group_id' => $groupId,
                'enabled' => $enabled ? 1 : 0,
            ];

            if ($action === 'create') {
                $existing = $this->repository->findByAccount($account);
                if ($existing !== null) {
                    throw new OpsCommandException("账号已存在：{$account}");
                }

                $adminId = $this->repository->create($data);

                return JsonCommandResult::success('账号已创建', ['admin_id' => $adminId]);
            }

            $existing = $this->repository->findByAccount($account);
            if ($existing === null) {
                throw new OpsCommandException("账号不存在：{$account}");
            }

            $adminId = (int) ($payload['admin_id'] ?? ($existing['id'] ?? 0));
            if ($adminId <= 0) {
                throw new OpsCommandException('admin_id 无效');
            }

            $this->repository->update($adminId, $data);

            return JsonCommandResult::success('账号已更新', ['admin_id' => $adminId]);
        } catch (OpsCommandException $e) {
            return JsonCommandResult::failure($e->getMessage());
        } catch (\Throwable $e) {
            return JsonCommandResult::failure($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $payload account + enabled(0|1)
     * @return array{exit_code: int, line: string, payload: array<string, mixed>}
     */
    public function setStatus(array $payload): array
    {
        try {
            $account = trim((string) ($payload['account'] ?? ''));
            if ($account === '') {
                throw new OpsCommandException('account 不能为空');
            }

            if (!array_key_exists('enabled', $payload)) {
                throw new OpsCommandException('enabled 不能为空，取值为 0 或 1');
            }

            $enabled = (int) $payload['enabled'];
            if (!in_array($enabled, [0, 1], true)) {
                throw new OpsCommandException('enabled 必须为 0 或 1');
            }

            $existing = $this->repository->findByAccount($account);
            if ($existing === null) {
                throw new OpsCommandException("账号不存在：{$account}");
            }

            $result = $this->repository->setEnabled($account, $enabled);
            if ($result['already_in_state']) {
                $message = $enabled ? '账号已是启用状态' : '账号已是禁用状态';
            } else {
                $message = $enabled ? '账号已开启' : '账号已关闭';
            }

            return JsonCommandResult::success($message, [
                'admin_id' => (int) ($existing['id'] ?? 0),
                'enabled' => $enabled,
                'already_in_state' => (bool) $result['already_in_state'],
            ]);
        } catch (OpsCommandException $e) {
            return JsonCommandResult::failure($e->getMessage());
        } catch (\Throwable $e) {
            return JsonCommandResult::failure($e->getMessage());
        }
    }
}
