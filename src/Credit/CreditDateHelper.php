<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Credit;

final class CreditDateHelper
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function resolveDueDate(string $timezone, $daysOpt, $dueDateOpt): int
    {
        if ($dueDateOpt !== null && $dueDateOpt !== '') {
            $dueDateStr = trim((string) $dueDateOpt);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDateStr)) {
                throw new \InvalidArgumentException('due-date 格式须为 YYYY-MM-DD');
            }

            [, $end] = self::dayBoundsFromDateStr($timezone, $dueDateStr);

            return $end;
        }

        $days = $daysOpt !== null && $daysOpt !== '' ? (int) $daysOpt : 3;
        if ($days < 1) {
            throw new \InvalidArgumentException('days 必须大于等于 1');
        }

        return self::nowTimestamp($timezone) - ($days * 86400);
    }

    public static function nowTimestamp(string $timezone): int
    {
        return (new \DateTime('now', new \DateTimeZone($timezone)))->getTimestamp();
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function dayBoundsFromDateStr(string $timezone, string $dateYmd): array
    {
        $tz = new \DateTimeZone($timezone);
        $d = new \DateTime($dateYmd . ' 00:00:00', $tz);
        $start = $d->getTimestamp();
        $d->setTime(23, 59, 59);
        $end = $d->getTimestamp();

        return [$start, $end];
    }

    public static function formatDueDateText(string $timezone, int $dueDate): string
    {
        return (new \DateTime('@' . $dueDate))
            ->setTimezone(new \DateTimeZone($timezone))
            ->format('Y-m-d H:i:s');
    }
}
