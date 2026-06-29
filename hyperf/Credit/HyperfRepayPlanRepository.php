<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf\Credit;

use Falconshop\LoanOps\Contract\RepayPlanRepositoryInterface;
use Hyperf\DbConnection\Db;

class HyperfRepayPlanRepository implements RepayPlanRepositoryInterface
{
    public function findLoanBySn(string $sn): ?array
    {
        $row = Db::table('loan')->where('sn', $sn)->first();

        return $row ? (array) $row : null;
    }

    public function findPlanBySnAndTerm(string $sn, int $termSeq): ?array
    {
        $row = Db::table('repay_plan')
            ->where('sn', $sn)
            ->where('term_seq', $termSeq)
            ->first();

        return $row ? (array) $row : null;
    }

    public function findPlanByPlanSn($planSn): ?array
    {
        $row = Db::table('repay_plan')->where('plan_sn', $planSn)->first();

        return $row ? (array) $row : null;
    }

    public function updateDueDate(string $sn, $planSn, int $termSeq, int $dueDate): int
    {
        $oldDueDate = 0;

        Db::beginTransaction();
        try {
            $plan = Db::table('repay_plan')->where('plan_sn', $planSn)->first();
            if ($plan) {
                $oldDueDate = (int) $plan->due_date;
            }

            Db::table('repay_plan')
                ->where('plan_sn', $planSn)
                ->update([
                    'due_date' => $dueDate,
                    'overdue_days' => 0,
                ]);

            if ($termSeq === 1) {
                Db::table('loan')->where('sn', $sn)->update(['due_date' => $dueDate]);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();

            throw $e;
        }

        return $oldDueDate;
    }
}
