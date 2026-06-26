<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf\Command;

use Falconshop\LoanOps\Admin\AdminAccountHandler;
use Falconshop\LoanOps\Hyperf\Admin\MarketAdminRepository;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * @Command
 */
class AdminAccountStatusCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('admin:account-status');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('设置贷超后台 admin 启用状态 enabled=0|1（toolbox 远程调用）');
        $this->addArgument('payload', InputArgument::REQUIRED, 'JSON: {"account":"...","enabled":0|1}');
        $this->addOption('json', 'j', InputOption::VALUE_NONE, '输出 JSON 结果');
    }

    public function handle()
    {
        $payload = $this->decodePayload((string) $this->input->getArgument('payload'));
        $handler = new AdminAccountHandler(new MarketAdminRepository());
        $result = $handler->setStatus($payload);
        $this->renderResult($result, (bool) $this->input->getOption('json'));

        return $result['exit_code'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $raw): array
    {
        $payload = json_decode($raw, true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array{exit_code: int, line: string, payload: array<string, mixed>} $result
     */
    private function renderResult(array $result, bool $asJson): void
    {
        if ($asJson) {
            $this->line($result['line']);

            return;
        }

        $message = (string) ($result['payload']['message'] ?? '');
        if ($result['exit_code'] === 0) {
            $this->info($message);
        } else {
            $this->error($message);
        }
    }
}
