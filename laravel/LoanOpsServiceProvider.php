<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Laravel;

use Falconshop\LoanOps\Laravel\Commands\AdminAccountStatusCommand;
use Falconshop\LoanOps\Laravel\Commands\AdminAccountSyncCommand;
use Illuminate\Support\ServiceProvider;

class LoanOpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AdminAccountSyncCommand::class,
                AdminAccountStatusCommand::class,
            ]);
        }
    }
}
