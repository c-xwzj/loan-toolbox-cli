<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Credit;

use Falconshop\LoanOps\Contract\RepayPlanRepositoryInterface;
use Falconshop\LoanOps\Exception\OpsCommandException;
use Falconshop\LoanOps\Support\JsonCommandResult;

class OverdueSimulateHandler
{
    /** @var RepayPlanRepositoryInterface */
    private $repository;

    /** @var string */
    private $timezone;

    public function __construct(RepayPlanRepositoryInterface $repository, string $timezone)
    {
        $this->repository = $repository;
        $this->timezone = $timezone !== '' ? $timezone : 'UTC';
    }

    /**
     * @param callable|null $collectionIntoRunner 执行 collection:into 的回调（由 Hyperf 命令注入）
     * @return array{exit_code: int, line: string, payload: array<string, mixed>}
     */
    public function handle(
        string $sn,
        int $termSeq,
        $daysOpt,
        $dueDateOpt,
        bool $skipInto,
        $collectionIntoRunner = null
    ): array {
        try {
            $sn = trim($sn);
            if ($sn === '') {
                throw new OpsCommandException('信贷订单号 sn 不能为空');
            }

            if ($termSeq < 1) {
                throw new OpsCommandException('term 必须大于等于 1');
            }

            $loan = $this->repository->findLoanBySn($sn);
            if ($loan === null) {
                throw new OpsCommandException("未找到放款单 sn={$sn}");
            }

            $plan = $this->repository->findPlanBySnAndTerm($sn, $termSeq);
            if ($plan === null) {
                throw new OpsCommandException("未找到还款计划 sn={$sn} term_seq={$termSeq}");
            }

            $status = (int) ($plan['status'] ?? 0);
            if (!in_array($status, [1, 3], true)) {
                throw new OpsCommandException('仅支持 status 为 1(待还) 或 3(逾期) 的还款计划');
            }

            $dueDate = CreditDateHelper::resolveDueDate($this->timezone, $daysOpt, $dueDateOpt);
            $oldDueDate = (int) ($plan['due_date'] ?? 0);
            $planSn = $plan['plan_sn'];

            $this->repository->updateDueDate($sn, $planSn, $termSeq, $dueDate);

            $this->logSimulate([
                'sn' => $sn,
                'plan_sn' => $planSn,
                'term_seq' => $termSeq,
                'old_due_date' => $oldDueDate,
                'new_due_date' => $dueDate,
                'skip_into' => $skipInto,
            ]);

            $intoRan = false;
            if (!$skipInto && is_callable($collectionIntoRunner)) {
                $collectionIntoRunner();
                $intoRan = true;
            }

            $planAfter = $this->repository->findPlanByPlanSn($planSn);
            $currentTime = CreditDateHelper::nowTimestamp($this->timezone);
            $overdueDays = (int) ceil(($currentTime - $dueDate) / 86400);

            return JsonCommandResult::success('逾期模拟完成', [
                'sn' => $sn,
                'plan_sn' => $planSn,
                'term_seq' => $termSeq,
                'old_due_date' => $oldDueDate,
                'due_date' => $dueDate,
                'due_date_text' => CreditDateHelper::formatDueDateText($this->timezone, $dueDate),
                'overdue_days' => isset($planAfter['overdue_days'])
                    ? (int) $planAfter['overdue_days']
                    : $overdueDays,
                'penalty' => isset($planAfter['penalty']) ? (float) $planAfter['penalty'] : null,
                'plan_status' => isset($planAfter['status']) ? (int) $planAfter['status'] : null,
                'into_ran' => $intoRan,
            ]);
        } catch (OpsCommandException $e) {
            return JsonCommandResult::failure($e->getMessage(), $sn !== '' ? ['sn' => $sn] : []);
        } catch (\InvalidArgumentException $e) {
            return JsonCommandResult::failure($e->getMessage(), $sn !== '' ? ['sn' => $sn] : []);
        } catch (\Throwable $e) {
            return JsonCommandResult::failure($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logSimulate(array $context): void
    {
        if (!class_exists('WLib\\WLog')) {
            return;
        }

        \WLib\WLog::record('creditOverdueSimulate', $context);
    }
}
