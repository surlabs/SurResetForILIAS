<?php

declare(strict_types=1);

namespace SurReset\classes\ui;

use Generator;
use ilAdvancedSelectionListGUI;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\UI\Component\Table\RowFactory;
use ilSurResetPlugin;
use ilTable2GUI;
use SurReset\classes\objects\Schedule;

class SurResetList extends ilTable2GUI
{
    private array $records = [];
    private ilSurResetPlugin $plugin;

    public function __construct($a_parent_obj, $a_parent_cmd)
    {
        global $DIC;

        $result = $DIC->database()->query(/** @lang text */ "SELECT * FROM silr_schedules");

        while ($record = $DIC->database()->fetchAssoc($result)) {
            $this->records[] = $record;
        }

        $this->plugin = ilSurResetPlugin::getInstance();

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->addColumn($this->plugin->txt("name"), "name");
        $this->addColumn($this->plugin->txt("count_of_programs_or_courses"), "count");
        $this->addColumn($this->plugin->txt("users"), "users");
        $this->addColumn($this->plugin->txt("frequency"), "frequency");
        $this->addColumn($this->plugin->txt("last_run"), "last_run");
        $this->addColumn($this->plugin->txt("actions"), "actions");

        $this->setRowTemplate("tpl.schedule_list_row.html", $this->plugin->getDirectory());

        $this->setData($this->getRows());
    }

    public function getRows(): array
    {
        $rows = [];

        foreach ($this->records as $record) {
            $record["frequency"] = $this->formatFrequencyText($record["frequency"], $record["frequency_data"]);

            $record["users"] = $this->plugin->txt(Schedule::TEXTS[$record["users"]]);

            $record["count"] = $this->countObjects((int) $record["id"]);

            $rows[] = $record;
        }

        return $rows;
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

    private function formatOrdinalNumber(int $number)
    {
        $lastDigit = $number % 10;
        $lastTwoDigits = $number % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 13) {
            return $number . $this->plugin->txt('frequency_ordinal_th');
        }
        
        switch ($lastDigit) {
            case 1:
                return $number . $this->plugin->txt('frequency_ordinal_st');
            case 2:
                return $number . $this->plugin->txt('frequency_ordinal_nd');
            case 3:
                return $number . $this->plugin->txt('frequency_ordinal_rd');
            default:
                return $number . $this->plugin->txt('frequency_ordinal_th');
        }
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

    protected function fillRow($a_set)
    {
        $this->tpl->setVariable("VAL_NAME", $a_set["name"]);
        $this->tpl->setVariable("VAL_COUNT", $a_set["count"]);
        $this->tpl->setVariable("VAL_USERS",  $a_set["users"]);
        $this->tpl->setVariable("VAL_FREQUENCY", $a_set["frequency"]);
        $this->tpl->setVariable("VAL_LAST_RUN", $a_set["last_run"]);

        $actions = new ilAdvancedSelectionListGUI();
        $actions->setListTitle($this->plugin->txt("actions"));
        $actions->setId("actions_" . $a_set["id"]);
        $actions->setUseImages(false);

        $actions->addItem(
            $this->lng->txt("edit"),
            "editSchedule",
            $this->ctrl->getLinkTarget($this->parent_obj, "editSchedule") . "&schedule_id=" . $a_set["id"]
        );

        $actions->addItem(
            $this->plugin->txt("run"),
            "runSchedule",
            $this->ctrl->getLinkTarget($this->parent_obj, "runSchedule") . "&schedule_id=" . $a_set["id"]
        );

        $actions->addItem(
            $this->lng->txt("delete"),
            "deleteSchedule",
            $this->ctrl->getLinkTarget($this->parent_obj, "deleteSchedule") . "&schedule_id=" . $a_set["id"]
        );

        $this->tpl->setVariable("VAL_ACTIONS", $actions->getHTML());
    }
}