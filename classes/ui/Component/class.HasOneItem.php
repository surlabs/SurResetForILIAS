<?php

declare(strict_types=1);

namespace SurReset\classes\ui\Component;

use ILIAS\Refinery\Constraint;
use ILIAS\Data;
use ILIAS\Refinery\Custom\Constraint as CustomConstraint;
use ilSurResetPlugin;

class HasOneItem extends CustomConstraint implements Constraint
{
    public function __construct(Data\Factory $data_factory)
    {
        global $DIC;

        parent::__construct(
            function ($value) {
                return is_array($value) && count($value) >= 1;
            },
            function ($txt, $value) {
                $plugin = ilSurResetPlugin::getInstance();

                return $plugin->txt("has_one_item_error");
            },
            $data_factory,
            $DIC->language()
        );
    }
}