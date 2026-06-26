<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf\Repay;

use Falconshop\LoanOps\Contract\RepayRecordRepositoryInterface;
use Hyperf\DbConnection\Db;

class RepayRecordRepository implements RepayRecordRepositoryInterface
{
    public function findActiveByRepayId(string $repayId): ?array
    {
        $row = Db::table('repay_record')
            ->where('repay_id', $repayId)
            ->where('destroy_status', 0)
            ->orderByDesc('created_at')
            ->first();

        return $row ? (array) $row : null;
    }
}
