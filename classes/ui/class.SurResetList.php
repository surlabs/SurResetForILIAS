<?php

declare(strict_types=1);

namespace classes\ui;

use classes\objects\Schedule;
use Generator;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ilSurResetPlugin;

class SurResetList implements DataRetrieval
{
    private array $records = [];
    private ilSurResetPlugin $plugin;

    public function __construct()
    {
        global $DIC;

        $result = $DIC->database()->query(/** @lang text */ "SELECT * FROM silr_schedules");

        while ($record = $DIC->database()->fetchAssoc($result)) {
            $this->records[] = $record;
        }

        $this->plugin = ilSurResetPlugin::getInstance();
    }

    public function getRows(
        DataRowBuilder $row_builder,
        ?array         $visible_column_ids,
        Range          $range,
        Order          $order,
        ?array         $filter_data,
        ?array         $additional_parameters
    ): Generator
    {
        $records_to_display = $this->getRecords();

        foreach ($records_to_display as $record) {
            $record["frequency"] = $this->formatFrequencyText($record["frequency"], $record["frequency_data"]);

            $record["users"] = $this->plugin->txt(Schedule::TEXTS[$record["users"]]);

            $record["count"] = $this->countObjects($record["id"]);

            yield $row_builder->buildDataRow((string) $record["id"], $record);
        }
    }

    public function getTotalRowCount(
        ?array $filter_data,
        ?array $additional_parameters
    ): ?int
    {
        return count($this->records);
    }

    protected function getRecords(): array
    {
        return $this->records;
    }

    private function formatFrequencyText(string $frequency, string $data): string
    {
        $data = json_decode($data, true);

        if (!is_array($data) || empty($frequency)) {
            return $this->plugin->txt('frequency_invalid_data');
        }

        switch ($frequency) {
            case 'manual':
                return $this->plugin->txt('frequency_manual');
            case 'daily':
                $interval = isset($data['interval']) ? (int)$data['interval'] : 1;
                if ($interval === 1) {
                    return $this->plugin->txt('frequency_daily_single');
                } else {
                    return sprintf($this->plugin->txt('frequency_daily_multiple'), $interval);
                }

            case 'weekly':
                $interval = isset($data['interval']) ? (int)$data['interval'] : 1;
                if ($interval === 1) {
                    return $this->plugin->txt('frequency_weekly_single');
                } else {
                    return sprintf($this->plugin->txt('frequency_weekly_multiple'), $interval);
                }

            case 'monthly':
                if (!isset($data['day'])) {
                    return $this->plugin->txt('frequency_monthly_invalid');
                }
                $day = (int)$data['day'];
                if ($day < 1 || $day > 31) {
                    return $this->plugin->txt('frequency_monthly_invalid_day');
                }
                return sprintf($this->plugin->txt('frequency_monthly'), $this->formatOrdinalNumber($day));

            case 'day_of_week':
                if (!isset($data['day'])) {
                    return $this->plugin->txt('frequency_day_of_week_invalid');
                }
                $dayIndex = (int)$data['day'];
                if ($dayIndex < 0 || $dayIndex > 6) {
                    return $this->plugin->txt('frequency_day_of_week_invalid_index');
                }
                $dayName = $this->getDayName($dayIndex);
                return sprintf($this->plugin->txt('frequency_day_of_week'), $dayName);

            case 'day_of_year':
                if (!isset($data['day']) || !isset($data['month'])) {
                    return $this->plugin->txt('frequency_day_of_year_invalid');
                }
                $day = (int)$data['day'];
                $month = (int)$data['month'];

                if ($month < 1 || $month > 12) {
                    return $this->plugin->txt('frequency_day_of_year_invalid_month');
                }

                if ($day < 1 || $day > 31) {
                    return $this->plugin->txt('frequency_day_of_year_invalid_day');
                }

                $monthName = $this->getMonthName($month);
                return sprintf($this->plugin->txt('frequency_day_of_year'), $this->formatOrdinalNumber($day), $monthName);

            case 'yearly':
                $interval = isset($data['interval']) ? (int)$data['interval'] : 1;
                if ($interval === 1) {
                    return $this->plugin->txt('frequency_yearly_single');
                } else {
                    return sprintf($this->plugin->txt('frequency_yearly_multiple'), $interval);
                }

            case 'hourly':
                $interval = isset($data['interval']) ? (int)$data['interval'] : 1;
                if ($interval === 1) {
                    return $this->plugin->txt('frequency_hourly_single');
                } else {
                    return sprintf($this->plugin->txt('frequency_hourly_multiple'), $interval);
                }

            case 'minutely':
                $interval = isset($data['interval']) ? (int)$data['interval'] : 1;
                if ($interval === 1) {
                    return $this->plugin->txt('frequency_minutely_single');
                } else {
                    return sprintf($this->plugin->txt('frequency_minutely_multiple'), $interval);
                }
            default:
                return sprintf($this->plugin->txt('frequency_unknown_type'), $frequency);
        }
    }

    private function getDayName($dayIndex): string
    {
        $dayKeys = [
            'frequency_day_sunday',
            'frequency_day_monday',
            'frequency_day_tuesday',
            'frequency_day_wednesday',
            'frequency_day_thursday',
            'frequency_day_friday',
            'frequency_day_saturday'
        ];

        return $this->plugin->txt($dayKeys[$dayIndex]);
    }

    private function getMonthName($monthIndex): string
    {
        $monthKeys = [
            1 => 'frequency_month_january',
            2 => 'frequency_month_february',
            3 => 'frequency_month_march',
            4 => 'frequency_month_april',
            5 => 'frequency_month_may',
            6 => 'frequency_month_june',
            7 => 'frequency_month_july',
            8 => 'frequency_month_august',
            9 => 'frequency_month_september',
            10 => 'frequency_month_october',
            11 => 'frequency_month_november',
            12 => 'frequency_month_december'
        ];

        return $this->plugin->txt($monthKeys[$monthIndex]);
    }

    private function formatOrdinalNumber(int $number): int|string
    {
        $lastDigit = $number % 10;
        $lastTwoDigits = $number % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 13) {
            return $number . $this->plugin->txt('frequency_ordinal_th');
        }

        return match ($lastDigit) {
            1 => $number . $this->plugin->txt('frequency_ordinal_st'),
            2 => $number . $this->plugin->txt('frequency_ordinal_nd'),
            3 => $number . $this->plugin->txt('frequency_ordinal_rd'),
            default => $number . $this->plugin->txt('frequency_ordinal_th'),
        };
    }

    private function countObjects(int $id): int
    {
        global $DIC;

        $result = $DIC->database()->queryF(
            /** @lang text */ "SELECT COUNT(*) AS count FROM silr_selected_objects WHERE schedule_id = %s",
            ['integer'],
            [$id]
        );

        $record = $DIC->database()->fetchAssoc($result);

        return (int) ($record['count'] ?? 0);
    }
}