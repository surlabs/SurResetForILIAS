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

class SurResetHistory extends ilTable2GUI
{
    private array $records = [];
    private ilSurResetPlugin $plugin;

    public function __construct($a_parent_obj, $a_parent_cmd)
    {
        global $DIC;

        $result = $DIC->database()->query(/** @lang text */ "SELECT * FROM silr_history ORDER BY date DESC");

        while ($record = $DIC->database()->fetchAssoc($result)) {
            $this->records[] = $record;
        }

        $this->plugin = ilSurResetPlugin::getInstance();

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->addColumn($this->plugin->txt("name"), "name");
        $this->addColumn($this->plugin->txt("date"), "date");
        $this->addColumn($this->plugin->txt("method"), "method");
        $this->addColumn($this->plugin->txt("count_of_affected_users"), "count");
        $this->addColumn($this->plugin->txt("duration"), "duration");
        $this->addColumn($this->plugin->txt("actions"), "actions");


        $this->setRowTemplate("tpl.schedule_history_row.html", $this->plugin->getDirectory());

        $this->setData($this->getRows());
    }

    public function getRows(): array
    {
        $rows = [];

        foreach ($this->records as $record) {
            $record["count_of_affected_users"] = $this->countUsers((int) $record["id"]);

            $record["method"] = $this->plugin->txt("method_" . Schedule::METHODS[$record["method"]]) ?? "Unknown";

            $record["name"] = $this->getScheduleName((int) $record["schedule_id"]);

            $record["duration"] = $record["duration"] . " ms";

            $rows[] = $record;
        }

        return $rows;
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

    protected function fillRow($a_set)
    {
        $this->tpl->setVariable("VAL_NAME", $a_set["name"]);
        $this->tpl->setVariable("VAL_DATE", $a_set["date"]);
        $this->tpl->setVariable("VAL_METHOD", $a_set["method"]);
        $this->tpl->setVariable("VAL_COUNT", $a_set["count_of_affected_users"]);
        $this->tpl->setVariable("VAL_DURATION", $a_set["duration"]);

        $actions = new ilAdvancedSelectionListGUI();
        $actions->setListTitle($this->plugin->txt("actions"));
        $actions->setId("actions_" . $a_set["id"]);
        $actions->setUseImages(false);

        $actions->addItem(
            $this->lng->txt("view"),
            "viewExecution",
            $this->ctrl->getLinkTarget($this->parent_obj, "viewExecution") . "&execution_id=" . $a_set["id"]
        );

        $this->tpl->setVariable("VAL_ACTIONS", $actions->getHTML());
    }
}