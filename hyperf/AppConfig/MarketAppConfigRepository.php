<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf\AppConfig;

use Falconshop\LoanOps\Contract\AppConfigRepositoryInterface;
use Hyperf\DbConnection\Db;

class MarketAppConfigRepository implements AppConfigRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $row = Db::table('app_config')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function existsByAppIdAndKey(int $appId, string $key, ?int $excludeId = null): bool
    {
        $query = Db::table('app_config')
            ->where('appId', $appId)
            ->where('key', $key);
        if ($excludeId !== null && $excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) Db::table('app_config')->insertGetId([
            'appId' => (int) $data['appId'],
            'key' => (string) $data['key'],
            'value' => (string) $data['value'],
            'note' => (string) $data['note'],
            'created' => $now,
            'updated' => $now,
        ]);
    }

    public function update(int $id, array $data): void
    {
        Db::table('app_config')->where('id', $id)->update([
            'appId' => (int) $data['appId'],
            'key' => (string) $data['key'],
            'value' => (string) $data['value'],
            'note' => (string) $data['note'],
            'updated' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $id): void
    {
        Db::table('app_config')->where('id', $id)->delete();
    }
}
