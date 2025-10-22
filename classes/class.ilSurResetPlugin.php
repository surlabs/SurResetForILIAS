<?php

declare(strict_types=1);

class ilSurResetPlugin extends ilUserInterfaceHookPlugin implements ilCronJobProvider
{
    public const PLUGIN_NAME = "SurReset";
    private static ilSurResetPlugin $instance;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            global $DIC;

            $component_repository = $DIC["component.repository"];

            $info = $component_repository->getPluginByName(self::PLUGIN_NAME);

            $component_factory = $DIC["component.factory"];

            $plugin_obj = $component_factory->getPlugin($info->getId());

            self::$instance = $plugin_obj;
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
}