<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Service;

use Topdata\TopdataTopsellerExportSW6\Enum\DateRangePreset;

class DateRangeResolver
{
    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     *
     * @throws \InvalidArgumentException
     */
    public function resolve(
        ?string $presetValue,
        ?string $startDateStr,
        ?string $endDateStr
    ): array {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($presetValue !== null && $presetValue !== '') {
            $preset = DateRangePreset::tryFrom($presetValue);
            if ($preset === null) {
                throw new \InvalidArgumentException(sprintf('Invalid date range preset "%s".', $presetValue));
            }

            return $this->resolvePreset($preset, $now);
        }

        try {
            $startDate = new \DateTimeImmutable((string) $startDateStr, new \DateTimeZone('UTC'));
            $endDate = new \DateTimeImmutable((string) $endDateStr, new \DateTimeZone('UTC'));
            $endDate = $endDate->setTime(23, 59, 59);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format for --start-date or --end-date. Use YYYY-MM-DD.', 0, $e);
        }

        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Start date cannot be after end date.');
        }

        return [$startDate, $endDate];
    }

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function resolvePreset(DateRangePreset $preset, \DateTimeImmutable $now): array
    {
        return match ($preset) {
            DateRangePreset::TODAY => [
                $now->setTime(0, 0, 0),
                $now->setTime(23, 59, 59),
            ],
            DateRangePreset::YESTERDAY => [
                $now->modify('-1 day')->setTime(0, 0, 0),
                $now->modify('-1 day')->setTime(23, 59, 59),
            ],
            DateRangePreset::THIS_WEEK => [
                $now->modify('this week monday')->setTime(0, 0, 0),
                $now->modify('this week sunday')->setTime(23, 59, 59),
            ],
            DateRangePreset::PREVIOUS_WEEK => [
                $now->modify('last week monday')->setTime(0, 0, 0),
                $now->modify('last week sunday')->setTime(23, 59, 59),
            ],
            DateRangePreset::THIS_MONTH => [
                $now->modify('first day of this month')->setTime(0, 0, 0),
                $now->modify('last day of this month')->setTime(23, 59, 59),
            ],
            DateRangePreset::PREVIOUS_MONTH => [
                $now->modify('first day of last month')->setTime(0, 0, 0),
                $now->modify('last day of last month')->setTime(23, 59, 59),
            ],
            DateRangePreset::THIS_YEAR => [
                $now->modify('first day of january this year')->setTime(0, 0, 0),
                $now->modify('last day of december this year')->setTime(23, 59, 59),
            ],
            DateRangePreset::PREVIOUS_YEAR => [
                $now->modify('first day of january last year')->setTime(0, 0, 0),
                $now->modify('last day of december last year')->setTime(23, 59, 59),
            ],
            DateRangePreset::LAST_7_DAYS => [
                $now->modify('-7 days')->setTime(0, 0, 0),
                $now->setTime(23, 59, 59),
            ],
            DateRangePreset::LAST_30_DAYS => [
                $now->modify('-30 days')->setTime(0, 0, 0),
                $now->setTime(23, 59, 59),
            ],
            DateRangePreset::LAST_365_DAYS => [
                $now->modify('-365 days')->setTime(0, 0, 0),
                $now->setTime(23, 59, 59),
            ],
        };
    }
}