<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Contract;

interface RepayRecordRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByRepayId(string $repayId): ?array;
}
