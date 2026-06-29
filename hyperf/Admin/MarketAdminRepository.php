<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf\Admin;

use Falconshop\LoanOps\Contract\AdminRepositoryInterface;
use Hyperf\DbConnection\Db;

class MarketAdminRepository implements AdminRepositoryInterface
{
    public function findByAccount(string $account): ?array
    {
        $row = Db::table('admin')->where('name', $account)->first();

        return $row ? (array) $row : null;
    }

    public function groupExists(int $groupId): bool
    {
        return Db::table('admin_group')
            ->where('id', $groupId)
            ->where('status', 1)
            ->exists();
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) Db::table('admin')->insertGetId([
            'username' => $data['username'],
            'name' => $data['account'],
            'password' => $data['password'],
            'enabled' => (int) $data['enabled'],
            'groupId' => (int) $data['group_id'],
            'created' => $now,
            'updated' => $now,
        ]);
    }

    public function update(int $id, array $data): void
    {
        Db::table('admin')->where('id', $id)->update([
            'username' => $data['username'],
            'name' => $data['account'],
            'password' => $data['password'],
            'enabled' => (int) $data['enabled'],
            'groupId' => (int) $data['group_id'],
            'updated' => date('Y-m-d H:i:s'),
        ]);
    }

    public function setEnabled(string $account, int $enabled): array
    {
        $row = $this->findByAccount($account);
        if ($row === null) {
            return ['already_in_state' => false];
        }

        $target = $enabled ? 1 : 0;
        $current = (int) ($row['enabled'] ?? 0);
        $alreadyInState = $current === $target;
        if (!$alreadyInState) {
            Db::table('admin')->where('id', $row['id'])->update([
                'enabled' => $target,
                'updated' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['already_in_state' => $alreadyInState];
    }

    public function updatePassword(string $account, string $password): void
    {
        Db::table('admin')->where('name', $account)->update([
            'password' => $password,
            'updated' => date('Y-m-d H:i:s'),
        ]);
    }
}
