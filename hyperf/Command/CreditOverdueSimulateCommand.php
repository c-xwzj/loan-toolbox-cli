<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf\Command;

use Falconshop\LoanOps\Credit\OverdueSimulateHandler;
use Falconshop\LoanOps\Hyperf\Credit\HyperfRepayPlanRepository;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreditOverdueSimulateCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('credit:overdue-simulate');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('测试环境：调整还款计划到期日并执行 collection:into（toolbox 远程调用）');
        $this->addArgument('sn', InputArgument::REQUIRED, '信贷订单号 loan.sn');
        $this->addOption('days', 'd', InputOption::VALUE_OPTIONAL, '逾期天数（相对当地今日，默认 3）', 3);
        $this->addOption('due-date', null, InputOption::VALUE_OPTIONAL, '指定到期日 YYYY-MM-DD（与 days 二选一）');
        $this->addOption('term', 't', InputOption::VALUE_OPTIONAL, '期次 term_seq，默认 1', 1);
        $this->addOption('skip-into', null, InputOption::VALUE_NONE, '仅改到期日，不执行 collection:into');
        $this->addOption('json', 'j', InputOption::VALUE_NONE, '输出 JSON 结果（供 toolbox 解析）');
    }

    public function handle()
    {
        $timezone = $this->resolveTimezone();
        $handler = new OverdueSimulateHandler(new HyperfRepayPlanRepository(), $timezone);
        $skipInto = (bool) $this->input->getOption('skip-into');
        $runner = null;
        if (!$skipInto) {
            $command = $this;
            $runner = static function () use ($command) {
                $command->call('collection:into');
            };
        }

        $result = $handler->handle(
            (string) $this->input->getArgument('sn'),
            (int) $this->input->getOption('term'),
            $this->input->getOption('days'),
            $this->input->getOption('due-date'),
            $skipInto,
            $runner
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

    private function resolveTimezone(): string
    {
        if (function_exists('loan_core_timezone')) {
            return loan_core_timezone();
        }

        if (function_exists('env')) {
            $tz = env('TIMEZONE');
            if (is_string($tz) && $tz !== '') {
                return $tz;
            }
        }

        $default = date_default_timezone_get();

        return is_string($default) && $default !== '' ? $default : 'UTC';
    }
}
