<?php
declare(strict_types=1);

use classes\objects\Schedule;
use ILIAS\Cron\Schedule\CronJobScheduleType;

class ilSurResetCron extends ilCronJob
{
    public const ID = 'sur_reset';
    private ilSurResetPlugin $plugin;

    public function __construct()
    {
        $this->plugin = ilSurResetPlugin::getInstance();
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getTitle(): string
    {
        return $this->plugin->txt("cron_title");
    }

    public function getDescription(): string
    {
        return $this->plugin->txt("cron_description");
    }

    public function hasAutoActivation(): bool
    {
        return true;
    }

    public function hasFlexibleSchedule(): bool
    {
        return true;
    }

    public function getDefaultScheduleType(): CronJobScheduleType
    {
        return CronJobScheduleType::SCHEDULE_TYPE_DAILY;
    }

    public function getDefaultScheduleValue(): ?int
    {
        return 1;
    }

    public function run(): ilCronJobResult
    {
        global $DIC;
        $cronResult = new ilCronJobResult();

        try {
            $query = /** @lang text */ "SELECT id FROM silr_schedules WHERE NOT frequency = 'manual'";
            $result = $DIC->database()->query($query);

            while ($row = $result->fetchAssoc()) {
                $schedule = new Schedule($row['id']);

                if ($schedule->shouldRun()) {
                    $schedule->run();
                }

                if ($schedule->shouldNotify()) {
                    $schedule->notify();
                }
            }

            $cronResult->setStatus(ilCronJobResult::STATUS_OK);
            $cronResult->setMessage("Test cronjob ran successfully.");
        } catch (Exception $e) {
            $cronResult->setStatus(ilCronJobResult::STATUS_FAIL);
            $cronResult->setMessage("Error running test cronjob: " . $e->getMessage());
        }

        return $cronResult;
    }
}
