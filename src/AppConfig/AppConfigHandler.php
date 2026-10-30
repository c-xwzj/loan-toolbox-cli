<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\AppConfig;

use Falconshop\LoanOps\Contract\AppConfigRepositoryInterface;
use Falconshop\LoanOps\Exception\OpsCommandException;
use Falconshop\LoanOps\Support\JsonCommandResult;

class AppConfigHandler
{
    /** @var AppConfigRepositoryInterface */
    private $repository;

    public function __construct(AppConfigRepositoryInterface $repository)
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
            if (!in_array($action, ['create', 'update', 'delete'], true)) {
                throw new OpsCommandException('action 必须为 create / update / delete');
            }

            if ($action === 'delete') {
                return $this->delete($payload);
            }

            $appId = (int) ($payload['app_id'] ?? $payload['appId'] ?? 0);
            if ($appId <= 0) {
                throw new OpsCommandException('app_id 无效');
            }

            $key = trim((string) ($payload['key'] ?? ''));
            if ($key === '') {
                throw new OpsCommandException('key 不能为空');
            }

            $value = (string) ($payload['value'] ?? '');
            if ($value === '') {
                throw new OpsCommandException('value 不能为空');
            }

            $note = trim((string) ($payload['note'] ?? ''));
            $data = [
                'appId' => $appId,
                'key' => $key,
                'value' => $value,
                'note' => $note,
            ];

            if ($action === 'create') {
                if ($this->repository->existsByAppIdAndKey($appId, $key)) {
                    throw new OpsCommandException('该 App 的配置 Key 已存在');
                }
                $id = $this->repository->create($data);

                return JsonCommandResult::success('创建成功', ['id' => $id]);
            }

            $id = (int) ($payload['id'] ?? 0);
            if ($id <= 0) {
                throw new OpsCommandException('id 无效');
            }
            $existing = $this->repository->findById($id);
            if ($existing === null) {
                throw new OpsCommandException('配置不存在');
            }
            if ($this->repository->existsByAppIdAndKey($appId, $key, $id)) {
                throw new OpsCommandException('该 App 的配置 Key 已存在');
            }
            $this->repository->update($id, $data);

            return JsonCommandResult::success('更新成功', ['id' => $id]);
        } catch (OpsCommandException $e) {
            return JsonCommandResult::failure($e->getMessage());
        } catch (\Throwable $e) {
            return JsonCommandResult::failure($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{exit_code: int, line: string, payload: array<string, mixed>}
     */
    private function delete(array $payload): array
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new OpsCommandException('id 无效');
        }
        $existing = $this->repository->findById($id);
        if ($existing === null) {
            throw new OpsCommandException('配置不存在');
        }
        $this->repository->delete($id);

        return JsonCommandResult::success('删除成功', ['id' => $id]);
    }
}
