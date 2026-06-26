<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Laravel\Commands;

use Falconshop\LoanOps\Admin\AdminAccountHandler;
use Falconshop\LoanOps\Laravel\Admin\CoreAdminRepository;
use Illuminate\Console\Command;

class AdminAccountDisableCommand extends Command
{
    /** @var string */
    protected $signature = 'admin:account-disable {payload} {--json}';

    /** @var string */
    protected $description = '关闭信贷后台 admin 账号（toolbox 远程调用）';

    public function handle(): int
    {
        $payload = json_decode((string) $this->argument('payload'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $handler = new AdminAccountHandler(new CoreAdminRepository());
        $result = $handler->disable($payload);
        $this->renderResult($result);

        return (int) $result['exit_code'];
    }

    /**
     * @param array{exit_code: int, line: string, payload: array<string, mixed>} $result
     */
    private function renderResult(array $result): void
    {
        if ($this->option('json')) {
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
