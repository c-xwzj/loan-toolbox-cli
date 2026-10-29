<?php

declare(strict_types=1);

namespace Falconshop\LoanOps\Credit;

final class CreditDateHelper
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function assertValidTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            throw new \InvalidArgumentException('timezone 不能为空');
        }

        try {
            new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("无效时区：{$timezone}");
        }

        return $timezone;
    }

    /**
     * 按国家时区解析到期日（当天 23:59:59）。
     * due-date 优先；否则相对该时区「今天」往前推 days 个自然日（默认 3）。
     *
     * @throws \InvalidArgumentException
     */
    public static function resolveDueDate(string $timezone, $daysOpt, $dueDateOpt): int
    {
        $tz = new \DateTimeZone(self::assertValidTimezone($timezone));

        if ($dueDateOpt !== null && $dueDateOpt !== '') {
            $ymd = trim((string) $dueDateOpt);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
                throw new \InvalidArgumentException('due-date 格式须为 YYYY-MM-DD');
            }
            $d = new \DateTime($ymd . ' 23:59:59', $tz);

            return $d->getTimestamp();
        }

        $days = ($daysOpt !== null && $daysOpt !== '') ? (int) $daysOpt : 3;
        if ($days < 1) {
            throw new \InvalidArgumentException('days 必须大于等于 1');
        }

        $d = new \DateTime('now', $tz);
        $d->setTime(23, 59, 59);
        $d->modify('-' . $days . ' days');

        return $d->getTimestamp();
    }

    /**
     * 由到期日反推起息/下单日 00:00:00（业务时区自然日）.
     * 与信贷 loan_core_due_end_ts_from_apply 一致：到期日 = 下单日 + term 天的 23:59:59.
     * 例：term=1、到期 10-28 23:59:59 → 起息 10-27 00:00:00；今天 10-29 计息天=2.
     */
    public static function startFromDue(int $dueDate, int $term, string $timezone = 'UTC'): int
    {
        if ($term < 1) {
            throw new \InvalidArgumentException('term 必须大于等于 1');
        }

        $tz = new \DateTimeZone(self::assertValidTimezone($timezone));
        $apply = (new \DateTime('@' . $dueDate))->setTimezone($tz)->setTime(0, 0, 0);
        $apply->modify('-' . $term . ' days');

        return $apply->getTimestamp();
    }

    /**
     * 下单日 0 点 → 今天 0 点的日历天数（最少 1），与 FeeCalculate::scheme2EarlySettleQuote 一致.
     */
    public static function interestDaysUntilToday(int $applyStartTs, string $timezone): int
    {
        $tz = new \DateTimeZone(self::assertValidTimezone($timezone));
        $applyDay = (new \DateTime('@' . $applyStartTs))->setTimezone($tz)->setTime(0, 0, 0);
        $today = (new \DateTime('now', $tz))->setTime(0, 0, 0);
        $days = (int) $applyDay->diff($today)->days;

        return max(1, $days);
    }

    public static function todayYmd(string $timezone): string
    {
        return (new \DateTime('now', new \DateTimeZone($timezone)))->format('Y-m-d');
    }

    public static function nowTimestamp(string $timezone): int
    {
        return (new \DateTime('now', new \DateTimeZone($timezone)))->getTimestamp();
    }

    public static function formatDueDateText(string $timezone, int $dueDate): string
    {
        return (new \DateTime('@' . $dueDate))
            ->setTimezone(new \DateTimeZone($timezone))
            ->format('Y-m-d H:i:s');
    }
}
