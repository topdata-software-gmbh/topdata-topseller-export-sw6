<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Enum;

enum DateRangePreset: string
{
    case TODAY = 'TODAY';
    case YESTERDAY = 'YESTERDAY';
    case THIS_WEEK = 'THIS_WEEK';
    case PREVIOUS_WEEK = 'PREVIOUS_WEEK';
    case THIS_MONTH = 'THIS_MONTH';
    case PREVIOUS_MONTH = 'PREVIOUS_MONTH';
    case THIS_YEAR = 'THIS_YEAR';
    case PREVIOUS_YEAR = 'PREVIOUS_YEAR';
    case LAST_7_DAYS = 'LAST_7_DAYS';
    case LAST_30_DAYS = 'LAST_30_DAYS';
    case LAST_365_DAYS = 'LAST_365_DAYS';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $preset): string => $preset->value,
            self::cases()
        );
    }
}