<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf;

use Falconshop\LoanOps\Hyperf\Command\AdminAccountStatusCommand;
use Falconshop\LoanOps\Hyperf\Command\AdminAccountSyncCommand;
use Falconshop\LoanOps\Hyperf\Command\RepayManualCallbackCommand;

class ConfigProvider
{
    public function __invoke(): array
    {
        $commands = [];

        if (class_exists('\\App\\Queue\\RepayCallbackQueue')) {
            $commands[] = RepayManualCallbackCommand::class;
        } else {
            $commands[] = AdminAccountSyncCommand::class;
            $commands[] = AdminAccountStatusCommand::class;
        }

        return [
            'commands' => $commands,
        ];
    }
}
