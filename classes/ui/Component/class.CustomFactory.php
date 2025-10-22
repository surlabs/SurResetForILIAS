<?php

declare(strict_types=1);

namespace Customizing\global\plugins\Services\UIComponent\UserInterfaceHook\SurReset\classes\ui\Component;

use Customizing\global\plugins\Services\UIComponent\UserInterfaceHook\SurReset\classes\ui\Component\Input\Field\ObjectSelector;
use Customizing\global\plugins\Services\UIComponent\UserInterfaceHook\SurReset\classes\ui\Component\Input\Field\MultipleSelector;

/**
 * Class CustomFactory
 */
class CustomFactory
{
    public function objectSelect(string $label, ?string $byline = null, array $allowed_types = []): ObjectSelector
    {
        return new ObjectSelector($label, $byline, $allowed_types);
    }

    public function roleSelect(string $label, ?string $byline = null): MultipleSelector
    {
        global $DIC;

        $options = [];
        $roles = $DIC->rbac()->review()->getRolesForIDs($DIC->rbac()->review()->getGlobalRoles(), false);

        foreach ($roles as $role) {
            $options[] = [
                'id' => $role["rol_id"],
                'title' => $role["title"],
            ];
        }

        return new MultipleSelector($label, $options,  $byline);
    }

    public function userSelect(string $label, ?string $byline = null): MultipleSelector
    {
        global $DIC;

        $options = [];

        $query = /** @lang text */ "SELECT usr_id, login, firstname, lastname FROM usr_data WHERE active = %s";
        $res = $DIC->database()->queryF($query, ['integer'], [1]);

        while ($row = $DIC->database()->fetchAssoc($res)) {
            $options[] = [
                'id' => (int)$row['usr_id'],
                'title' => $row['firstname'] . ' ' . $row['lastname'] . ' (' . $row['login'] . ')',
            ];
        }

        return new MultipleSelector($label, $options, $byline);
    }
}