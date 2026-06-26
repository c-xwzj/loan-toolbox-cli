<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Hyperf\Repay;

use Falconshop\LoanOps\Contract\RepayCallbackPusherInterface;
use Falconshop\LoanOps\Exception\OpsCommandException;
use Psr\Container\ContainerInterface;

class AppRepayCallbackPusher implements RepayCallbackPusherInterface
{
    /** @var string */
    private $queueClass;

    public function __construct(string $queueClass = '\\App\\Queue\\RepayCallbackQueue')
    {
        $this->queueClass = $queueClass;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function push(array $params): bool
    {
        if (!class_exists($this->queueClass)) {
            throw new OpsCommandException('未找到 ' . $this->queueClass . '，请确认支付项目已配置还款回调队列');
        }

        $container = $this->getContainer();
        $queue = $container->get($this->queueClass);

        return (bool) $queue->push($params);
    }

    private function getContainer(): ContainerInterface
    {
        if (class_exists(\Hyperf\Context\ApplicationContext::class)) {
            return \Hyperf\Context\ApplicationContext::getContainer();
        }

        if (class_exists(\Hyperf\Utils\ApplicationContext::class)) {
            return \Hyperf\Utils\ApplicationContext::getContainer();
        }

        throw new OpsCommandException('无法获取 Hyperf 容器');
    }
}
