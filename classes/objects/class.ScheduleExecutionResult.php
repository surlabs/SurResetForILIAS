<?php

declare(strict_types=1);

namespace SurReset\classes\objects;

class ScheduleExecutionResult
{
    private int $schedule_id;
    private string $date;
    private int $method;
    private float $start_time;

    public function __construct(int $schedule_id, int $method)
    {
        $this->schedule_id = $schedule_id;
        $this->date = date("Y-m-d H:i:s");
        $this->method = $method;
        $this->start_time = microtime(true);
    }

    public static function getById(int $execution_id): array
    {
        global $DIC;

        $db = $DIC->database();

        $query = "SELECT * FROM silr_history WHERE id = %s";
        $result = $db->queryF($query, ["integer"], [$execution_id]);

        if ($result->numRows() === 0) {
            return [];
        }

        $row = $result->fetchAssoc();

        $affectedUsers = [];

        $affectedQuery = "SELECT user_id FROM silr_users_affected WHERE execution_id = %s";

        $affectedResult = $db->queryF($affectedQuery, ["integer"], [$execution_id]);

        while ($affectedRow = $affectedResult->fetchAssoc()) {
            $affectedUsers[] = (int) $affectedRow['user_id'];
        }

        $schedule_name = "Unknown Schedule";
        $scheduleQuery = "SELECT name FROM silr_schedules WHERE id = %s";
        $scheduleResult = $db->queryF($scheduleQuery, ["integer"], [$row['schedule_id']]);
        if ($scheduleResult->numRows() > 0) {
            $scheduleRow = $scheduleResult->fetchAssoc();
            $schedule_name = $scheduleRow['name'];
        }

        $affectedObjects = [];

        $objectsQuery = "SELECT object_id FROM silr_objects_affected WHERE execution_id = %s";

        $objectsResult = $db->queryF($objectsQuery, ["integer"], [$execution_id]);

        while ($objectRow = $objectsResult->fetchAssoc()) {
            $affectedObjects[] = (int) $objectRow['object_id'];
        }

        return [
            'id' => (int) $row['id'],
            'schedule_name' => $schedule_name,
            'date' => $row['date'],
            'method' => (int) $row['method'],
            'duration' => (int) $row['duration'],
            'affected_users' => $affectedUsers,
            'affected_objects' => $affectedObjects,
        ];
    }

    public function save(array $affected_users, array $affected_objects): void
    {
        global $DIC;

        $db = $DIC->database();

        $id = $db->nextId('silr_history');

        $duration = (microtime(true) - $this->start_time) * 1000;

        $db->insert(
            'silr_history',
            [
                'id' => ["integer", $id],
                'schedule_id' => ["integer", $this->schedule_id],
                'date' => ["text", $this->date],
                'method' => ["integer", $this->method],
                'duration' => ["integer", (int) $duration],
            ]
        );

        foreach ($affected_users as $user_id) {
            $db->insert(
                'silr_users_affected',
                [
                    'execution_id' => ["integer", $id],
                    'user_id' => ["integer", $user_id],
                ]
            );
        }

        foreach ($affected_objects as $object) {
            $db->insert(
                'silr_objects_affected',
                [
                    'execution_id' => ["integer", $id],
                    'object_id' => ["integer", $object["id"]],
                ]
            );
        }
    }
}