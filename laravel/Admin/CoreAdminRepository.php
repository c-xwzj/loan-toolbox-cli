<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Laravel\Admin;

use Falconshop\LoanOps\Contract\AdminRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CoreAdminRepository implements AdminRepositoryInterface
{
    public function findByAccount(string $account): ?array
    {
        $row = DB::table('admin')->where('account', $account)->first();

        return $row ? (array) $row : null;
    }

    public function groupExists(int $groupId): bool
    {
        return DB::table('admin_group')
            ->where('id', $groupId)
            ->where('status', 1)
            ->exists();
    }

    public function create(array $data): int
    {
        return (int) DB::table('admin')->insertGetId([
            'username' => $data['username'],
            'account' => $data['account'],
            'password' => $data['password'],
            'group_id' => (int) $data['group_id'],
            'status' => (int) $data['enabled'] === 1 ? 1 : 2,
        ]);
    }

    public function update(int $id, array $data): void
    {
        DB::table('admin')->where('id', $id)->update([
            'username' => $data['username'],
            'account' => $data['account'],
            'password' => $data['password'],
            'group_id' => (int) $data['group_id'],
            'status' => (int) $data['enabled'] === 1 ? 1 : 2,
        ]);
    }

    public function disable(string $account): array
    {
        $row = $this->findByAccount($account);
        if ($row === null) {
            return ['already_disabled' => false];
        }

        $alreadyDisabled = (int) ($row['status'] ?? 0) !== 1;
        if (!$alreadyDisabled) {
            DB::table('admin')->where('id', $row['id'])->update(['status' => 2]);
        }

        return ['already_disabled' => $alreadyDisabled];
    }
}
