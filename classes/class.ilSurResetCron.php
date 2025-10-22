<?php
declare(strict_types=1);

use SurReset\classes\objects\Schedule;

class ilSurResetCron extends ilCronJob
{
    public const ID = 'sur_reset';
    private $plugin;

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

    public function getDefaultScheduleType(): int
    {
        return self::SCHEDULE_TYPE_DAILY;
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
            $query = /** @lang text */
                "SELECT id FROM silr_schedules WHERE NOT frequency = 'manual'";
            $result = $DIC->database()->query($query);

            while ($row = $result->fetchAssoc()) {
                $schedule = new Schedule((int) $row['id']);

                if ($schedule->shouldRun()) {
                    $schedule->run();

                    $schedule->sendNotification(
                        $schedule->getAfterRestartSubject(),
                        $schedule->getAfterRestartTemplate()
                    );
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
