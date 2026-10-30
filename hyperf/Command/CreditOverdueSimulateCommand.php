<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf\Command;

use Falconshop\LoanOps\Credit\CreditDateHelper;
use Falconshop\LoanOps\Credit\OverdueSimulateHandler;
use Falconshop\LoanOps\Hyperf\Credit\HyperfRepayPlanRepository;
use Falconshop\LoanOps\Support\JsonCommandResult;
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
        $this->setDescription('测试环境：模拟逾期 N 天（拨到期日/下单日并对齐利息天数，再入催）');
        $this->addArgument('sn', InputArgument::REQUIRED, '信贷订单号 loan.sn');
        $this->addOption('days', 'd', InputOption::VALUE_OPTIONAL, '逾期几天（相对今天；注意利息天数常 = 产品天数 + 逾期天数，默认 3）', 3);
        $this->addOption('due-date', null, InputOption::VALUE_OPTIONAL, '指定到期日 YYYY-MM-DD（按国家时区解释）');
        $this->addOption('timezone', null, InputOption::VALUE_OPTIONAL, '国家时区，如 Africa/Lagos（Toolbox 必传）');
        $this->addOption('term', 't', InputOption::VALUE_OPTIONAL, '期次 term_seq，默认 1', 1);
        $this->addOption('skip-into', null, InputOption::VALUE_NONE, '仅改到期日，不执行 collection:into');
        $this->addOption('json', 'j', InputOption::VALUE_NONE, '输出 JSON 结果');
    }

    public function handle()
    {
        try {
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
        } catch (\InvalidArgumentException $e) {
            $result = JsonCommandResult::failure($e->getMessage());
        }

        if ((bool) $this->input->getOption('json')) {
            $this->line($result['line']);
        } else {
            $message = (string) ($result['payload']['message'] ?? '');
            $result['exit_code'] === 0 ? $this->info($message) : $this->error($message);
        }

        return $result['exit_code'];
    }

    /** --timezone > loan_core_timezone() > env(TIMEZONE) > UTC */
    private function resolveTimezone(): string
    {
        $tz = trim((string) ($this->input->getOption('timezone') ?: ''));
        if ($tz === '' && function_exists('loan_core_timezone')) {
            $tz = trim((string) loan_core_timezone());
        }
        if ($tz === '' && function_exists('env')) {
            $tz = trim((string) (env('TIMEZONE') ?: ''));
        }

        return CreditDateHelper::assertValidTimezone($tz !== '' ? $tz : 'UTC');
    }
}
