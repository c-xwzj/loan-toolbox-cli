<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Contract;

interface AppConfigRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    public function existsByAppIdAndKey(int $appId, string $key, ?int $excludeId = null): bool;

    /**
     * @param array{appId:int,key:string,value:string,note:string} $data
     */
    public function create(array $data): int;

    /**
     * @param array{appId:int,key:string,value:string,note:string} $data
     */
    public function update(int $id, array $data): void;

    public function delete(int $id): void;
}
