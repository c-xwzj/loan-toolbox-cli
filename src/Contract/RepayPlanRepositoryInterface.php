<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Contract;

interface RepayPlanRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findLoanBySn(string $sn): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findPlanBySnAndTerm(string $sn, int $termSeq): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findPlanByPlanSn($planSn): ?array;

    /**
     * @throws \Throwable
     */
    public function updateDueDate(string $sn, $planSn, int $termSeq, int $dueDate): int;
}
