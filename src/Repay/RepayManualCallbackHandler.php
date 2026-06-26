<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Repay;

use Falconshop\LoanOps\Contract\RepayCallbackPusherInterface;
use Falconshop\LoanOps\Contract\RepayRecordRepositoryInterface;
use Falconshop\LoanOps\Exception\OpsCommandException;
use Falconshop\LoanOps\Support\JsonCommandResult;

class RepayManualCallbackHandler
{
    /** @var RepayRecordRepositoryInterface */
    private $repayRecordRepository;

    /** @var RepayCallbackPusherInterface */
    private $callbackPusher;

    /** @var string */
    private $queueName;

    public function __construct(
        RepayRecordRepositoryInterface $repayRecordRepository,
        RepayCallbackPusherInterface $callbackPusher,
        string $queueName = 'repay-callback-queue'
    ) {
        $this->repayRecordRepository = $repayRecordRepository;
        $this->callbackPusher = $callbackPusher;
        $this->queueName = $queueName;
    }

    /**
     * @return array{exit_code: int, line: string, payload: array<string, mixed>}
     */
    public function handle(string $repayId, $amountArg, ?string $txnArg = null): array
    {
        try {
            $repayId = trim($repayId);
            if ($repayId === '') {
                throw new OpsCommandException('repay_id 不能为空');
            }

            if (!is_numeric($amountArg)) {
                throw new OpsCommandException('还款金额必须为数字');
            }

            $amount = (int) $amountArg;
            if ($amount < 0) {
                throw new OpsCommandException('还款金额不能为负数');
            }

            $repayInfo = $this->repayRecordRepository->findActiveByRepayId($repayId);
            if ($repayInfo === null) {
                throw new OpsCommandException("未找到有效还款记录 repay_id={$repayId}");
            }

            $txn = $txnArg !== null && $txnArg !== ''
                ? (string) $txnArg
                : 'MANUAL-' . time();

            $params = [
                'repay_id' => (int) $repayInfo['repay_id'],
                'merchant_id' => (int) $repayInfo['merchant_id'],
                'success' => 1,
                'channel' => (string) $repayInfo['channel'],
                'account_name' => $amount,
                'message' => '',
                'fee' => 0,
                'txn' => $txn,
                'repay_time' => time(),
            ];

            $ok = $this->callbackPusher->push($params);
            $this->logManualCallback($params, $ok);

            if ($ok) {
                return JsonCommandResult::success('入队成功', [
                    'repay_id' => $params['repay_id'],
                    'amount' => $amount,
                    'txn' => $txn,
                    'queue' => $this->queueName,
                    'params' => $params,
                ]);
            }

            return JsonCommandResult::failure('入队失败', [
                'repay_id' => $params['repay_id'],
                'params' => $params,
            ]);
        } catch (OpsCommandException $e) {
            return JsonCommandResult::failure($e->getMessage(), [
                'repay_id' => $repayId,
            ]);
        } catch (\Throwable $e) {
            return JsonCommandResult::failure($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function logManualCallback(array $params, bool $result): void
    {
        if (!class_exists('WLib\\WLog')) {
            return;
        }

        \WLib\WLog::record('repayManualCallback', [
            'message' => '手动还款回调入队',
            'params' => $params,
            'result' => $result,
        ]);
    }
}
