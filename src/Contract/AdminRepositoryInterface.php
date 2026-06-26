<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Contract;

interface AdminRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByAccount(string $account): ?array;

    public function groupExists(int $groupId): bool;

    /**
     * @param array<string, mixed> $data normalized: username, account, password, group_id, enabled
     */
    public function create(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void;

    /**
     * @return array{already_disabled: bool}
     */
    public function disable(string $account): array;
}
