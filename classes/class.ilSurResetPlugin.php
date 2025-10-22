<?php

declare(strict_types=1);

class ilSurResetPlugin extends ilCronHookPlugin
{
    public const PLUGIN_NAME = "SurReset";
    private static $instance;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getCronJobInstance($jobId): ilCronJob
    {
        if ($jobId === ilSurResetCron::ID) {
            return new ilSurResetCron();
        }

        throw new OutOfBoundsException("No cron job found with ID: " . $jobId);
    }

    public function getCronJobInstances(): array
    {
        return [new ilSurResetCron()];
    }

    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }
}