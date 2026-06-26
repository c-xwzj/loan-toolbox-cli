<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf\Command;

use Falconshop\LoanOps\Hyperf\Repay\AppRepayCallbackPusher;
use Falconshop\LoanOps\Hyperf\Repay\RepayRecordRepository;
use Falconshop\LoanOps\Repay\RepayManualCallbackHandler;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class RepayManualCallbackCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('repay:manual-callback');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('手动触发还款回调入队：repay_id、还款金额，可选 txn（toolbox 远程调用）');
        $this->addArgument('repay_id', InputArgument::REQUIRED, '还款流水号（repay_record.repay_id）');
        $this->addArgument('amount', InputArgument::REQUIRED, '还款金额（整数）');
        $this->addArgument('txn', InputArgument::OPTIONAL, '第三方流水号，不传则 MANUAL-{timestamp}', null);
        $this->addOption('json', 'j', InputOption::VALUE_NONE, '输出 JSON 结果（供 toolbox 解析）');
    }

    public function handle()
    {
        $handler = new RepayManualCallbackHandler(
            new RepayRecordRepository(),
            new AppRepayCallbackPusher()
        );

        $result = $handler->handle(
            (string) $this->input->getArgument('repay_id'),
            $this->input->getArgument('amount'),
            $this->input->getArgument('txn')
        );

        $asJson = (bool) $this->input->getOption('json');
        if ($asJson) {
            $this->line($result['line']);
        } else {
            $message = (string) ($result['payload']['message'] ?? '');
            if ($result['exit_code'] === 0) {
                $this->info($message);
            } else {
                $this->error($message);
            }
        }

        return $result['exit_code'];
    }
}
