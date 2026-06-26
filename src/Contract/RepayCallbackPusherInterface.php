<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Contract;

interface RepayCallbackPusherInterface
{
    /**
     * @param array<string, mixed> $params
     */
    public function push(array $params): bool;
}
