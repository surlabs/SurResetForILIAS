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

class SurResetHistory implements DataRetrieval
{
    private array $records = [];
    private ilSurResetPlugin $plugin;

    public function __construct()
    {
        global $DIC;

        $result = $DIC->database()->query(/** @lang text */ "SELECT * FROM silr_history ORDER BY date DESC");

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
            $record["count_of_affected_users"] = $this->countUsers($record["id"]);

            $record["method"] = $this->plugin->txt("method_" . Schedule::METHODS[$record["method"]]) ?? "Unknown";

            $record["name"] = $this->getScheduleName((int) $record["schedule_id"]);

            $record["duration"] = $record["duration"] . " ms";

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

    private function countUsers(int $id): int
    {
        global $DIC;

        $result = $DIC->database()->queryF(
            /** @lang text */ "SELECT COUNT(*) AS count FROM silr_users_affected WHERE execution_id = %s",
            ['integer'],
            [$id]
        );

        $record = $DIC->database()->fetchAssoc($result);

        return (int) ($record['count'] ?? 0);
    }

    private function getScheduleName(int $schedule_id)
    {
        global $DIC;

        $result = $DIC->database()->queryF(
        /** @lang text */ "SELECT name FROM silr_schedules WHERE id = %s",
            ['integer'],
            [$schedule_id]
        );

        $record = $DIC->database()->fetchAssoc($result);

        return $record['name'] ?? '';
    }
}