<?php

declare(strict_types=1);

namespace classes\objects;

use DateInterval;
use DateInvalidOperationException;
use DateMalformedIntervalStringException;
use DateMalformedStringException;
use DateTime;
use Exception;
use ilChangeEvent;
use ilLanguage;
use ilLogger;
use ilLoggerFactory;
use ilLogLevel;
use ilLPMarks;
use ilMail;
use ilObjCourseReference;
use ilObject;
use ilObjectLP;
use ilObjSAHSLearningModule;
use ilObjSCORM2004LearningModule;
use ilObjSCORMLearningModule;
use ilObjTest;
use ilObjUser;
use ilStudyProgrammeTreeException;
use ilSurResetPlugin;
use ilTestLP;

class Schedule
{
    public const USERS_ALL = 1;
    public const USERS_SPECIFIC = 2;
    public const USERS_BY_ROLE = 3;
    public const USERS_ALL_EXCEPT = 4;
    const METHOD_MANUAL = 1;
    const METHOD_AUTOMATIC = 2;

    private int $id;
    private string $name;
    private int $users;
    private string $frequency;
    private string $frequency_data = '';
    private string $created_at;
    private bool $email_notifications = false;
    private int $days_in_advance = 0;
    private string $notification_subject = '';
    private string $notification_template = '';
    private ?string $last_run;
    private ?string $last_notification;
    private ilLogger $logger;

    public const TEXTS = [
        self::USERS_ALL => "all_users",
        self::USERS_SPECIFIC => "specific_users",
        self::USERS_BY_ROLE => "users_by_role",
        self::USERS_ALL_EXCEPT => "all_users_except",
    ];

    public const METHODS = [
        self::METHOD_MANUAL => "manual",
        self::METHOD_AUTOMATIC => "automatic",
    ];

    /**
     * @throws Exception
     */
    public function __construct(int $id = 0)
    {
        $this->id = $id;

        if ($this->id > 0) {
            $this->load($id);
        }

        $this->logger = ilLoggerFactory::getLogger('silr.schedule');
    }

    /**
     * @throws Exception
     */
    public function load(int $id): void
    {
        global $DIC;

        $query = /** @lang text */ "SELECT * FROM silr_schedules WHERE id = %s";
        $result = $DIC->database()->queryF($query, ['integer'], [$id]);

        if ($record = $DIC->database()->fetchAssoc($result)) {
            foreach ($record as $key => $value) {
                if ($key === 'email_notifications') {
                    $value = (bool) $value;
                }

                $this->{$key} = $value;
            }
        } else {
            throw new Exception("Schedule with ID $id not found.");
        }
    }

    public function delete(): bool
    {
        global $DIC;

        $query = /** @lang text */ "DELETE FROM silr_schedules WHERE id = %s";
        $result = $DIC->database()->manipulateF($query, ['integer'], [$this->id]);

        if ($result) {
            $this->logger->log("Schedule with ID $this->id was deleted by user " . $DIC->user()->getLogin());

            return true;
        } else {
            $this->logger->log("Error deleting schedule with ID $this->id by user " . $DIC->user()->getLogin(), ilLogLevel::ERROR);

            return false;
        }
    }

    public function save(): void
    {
        if ($this->id > 0) {
            $this->update();
        } else {
            $this->insert();
        }
    }

    private function insert(): void
    {
        global $DIC;

        $this->id = $DIC->database()->nextId('silr_schedules');

        $DIC->database()->insert('silr_schedules', [
            'id' => ['integer', $this->id],
            'name' => ['text', $this->name],
            'users' => ['integer', $this->users],
            'frequency' => ['text', $this->frequency],
            'frequency_data' => ['text', $this->frequency_data],
            'created_at' => ['timestamp', $this->created_at ?? date('Y-m-d H:i:s')],
            'email_notifications' => ['integer', (int) $this->email_notifications],
            'days_in_advance' => ['integer', $this->days_in_advance],
            'notification_subject' => ['text', $this->notification_subject],
            'notification_template' => ['text', $this->notification_template],
            'last_run' => ['timestamp', $this->last_run ?? null],
            'last_notification' => ['timestamp', $this->last_notification ?? null],

        ]);

        $this->logger->log("New schedule with ID $this->id was created by user " . $DIC->user()->getLogin());
    }

    private function update(): void
    {
        global $DIC;

        $DIC->database()->update('silr_schedules', [
            'name' => ['text', $this->name],
            'users' => ['integer', $this->users],
            'frequency' => ['text', $this->frequency],
            'frequency_data' => ['text', $this->frequency_data],
            'email_notifications' => ['integer', (int) $this->email_notifications],
            'days_in_advance' => ['integer', $this->days_in_advance],
            'notification_subject' => ['text', $this->notification_subject],
            'notification_template' => ['text', $this->notification_template],
            'last_run' => ['timestamp', $this->last_run ?? null],
            'last_notification' => ['timestamp', $this->last_notification ?? null],
        ], [
            'id' => ['integer', $this->id]
        ]);

        $this->logger->log("Schedule with ID $this->id was updated by user " . $DIC->user()->getLogin());
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUsers(): int
    {
        return $this->users;
    }

    public function setUsers(int $users): void
    {
        $this->users = $users;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function getFrequencyData(): array
    {
        return json_decode($this->frequency_data, true);
    }

    public function setFrequency(string $frequency, array $frequency_data = []): void
    {
        $this->frequency = $frequency;
        $this->frequency_data = json_encode($frequency_data);
    }

    public function getCreatedAt(): string
    {
        return $this->created_at ?? date('Y-m-d H:i:s');
    }

    public function isEmailEnabled(): bool
    {
        return $this->email_notifications;
    }

    public function setEmailEnabled(bool $enabled): void
    {
        $this->email_notifications = $enabled;
    }

    public function getDaysInAdvance(): int
    {
        return $this->days_in_advance;
    }

    public function setDaysInAdvance(int $days): void
    {
        $this->days_in_advance = $days;
    }

    public function getNotificationSubject(): string
    {
        return $this->notification_subject;
    }

    public function setNotificationSubject(string $subject): void
    {
        $this->notification_subject = $subject;
    }

    public function getNotificationTemplate(): string
    {
        return $this->notification_template;
    }

    public function setNotificationTemplate(string $template): void
    {
        $this->notification_template = $template;
    }

    public function getLastRun(): ?string
    {
        return $this->last_run;
    }

    public function setLastRun(?string $last_run): void
    {
        $this->last_run = $last_run;
    }

    public function getLastNotification(): ?string
    {
        return $this->last_notification;
    }

    public function setLastNotification(?string $last_notification): void
    {
        $this->last_notification = $last_notification;
    }

    /**
     * @throws Exception
     */
    public function saveObjectsData(array $objects): void
    {
        global $DIC;

        $query = /** @lang text */ "DELETE FROM silr_selected_objects WHERE schedule_id = %s";

        $DIC->database()->manipulateF($query, ['integer'], [$this->id]);

        foreach ($objects as $object) {
            $query = /** @lang text */ "INSERT INTO silr_selected_objects (schedule_id, object_id) VALUES (%s, %s) ON DUPLICATE KEY UPDATE object_id = %s";

            $DIC->database()->manipulateF(
                $query,
                ['integer', 'integer', 'integer'],
                [$this->id, $object['id'], $object['id']]
            );
        }

        $this->logger->log("Objects data for schedule with ID $this->id was saved by user " . $DIC->user()->getLogin());
    }

    public function saveUsersData(array $users): void
    {
        global $DIC;

        if ($this->users === self::USERS_SPECIFIC) {
            $query = /** @lang text */ "DELETE FROM silr_selected_users WHERE schedule_id = %s";

            $DIC->database()->manipulateF($query, ['integer'], [$this->id]);

            foreach ($users["specific_users"] as $user) {
                $query = /** @lang text */ "INSERT INTO silr_selected_users (schedule_id, user_id) VALUES (%s, %s) ON DUPLICATE KEY UPDATE user_id = %s";

                $DIC->database()->manipulateF(
                    $query,
                    ['integer', 'integer', 'integer'],
                    [$this->id, $user['id'], $user['id']]
                );
            }
        } elseif ($this->users === self::USERS_BY_ROLE) {
            $query = /** @lang text */ "DELETE FROM silr_selected_roles WHERE schedule_id = %s";

            $DIC->database()->manipulateF($query, ['integer'], [$this->id]);

            foreach ($users["role"] as $role) {
                $query = /** @lang text */ "INSERT INTO silr_selected_roles (schedule_id, role_id) VALUES (%s, %s) ON DUPLICATE KEY UPDATE role_id = %s";

                $DIC->database()->manipulateF(
                    $query,
                    ['integer', 'integer', 'integer'],
                    [$this->id, $role['id'], $role['id']]
                );
            }
        } elseif ($this->users === self::USERS_ALL_EXCEPT) {
            $query = /** @lang text */ "DELETE FROM silr_excluded_users WHERE schedule_id = %s";

            $DIC->database()->manipulateF($query, ['integer'], [$this->id]);

            foreach ($users["excluded_users"] as $user) {
                $query = /** @lang text */ "INSERT INTO silr_excluded_users (schedule_id, user_id) VALUES (%s, %s) ON DUPLICATE KEY UPDATE user_id = %s";

                $DIC->database()->manipulateF(
                    $query,
                    ['integer', 'integer', 'integer'],
                    [$this->id, $user['id'], $user['id']]
                );
            }
        }

        $this->logger->log("Users data for schedule with ID $this->id was saved by user " . $DIC->user()->getLogin());
    }

    public function getObjectsData(): array
    {
        global $DIC;

        $query = /** @lang text */ "SELECT object_id FROM silr_selected_objects WHERE schedule_id = %s";
        $result = $DIC->database()->queryF($query, ['integer'], [$this->id]);

        $objects = [];
        while ($record = $DIC->database()->fetchAssoc($result)) {
            $objects[] = ["id" => $record['object_id']];
        }

        return $objects;
    }

    public function getObjectsDataToDisplay(): array
    {
        $data = $this->getObjectsData();

        $objects = [];

        foreach ($data as $object) {
            $ref_id = $object['id'];
            $obj_id = ilObject::_lookupObjectId($ref_id);
            $type = ilObject::_lookupType($obj_id);
            $title = ilObject::_lookupTitle($obj_id);

            $url = "goto.php?target={$type}_$ref_id";

            $objects[] = [
                'title' => $title,
                'url'   => $url
            ];
        }

        return $objects;
    }

    public function getUsersData(): array
    {
        global $DIC;

        if ($this->users === self::USERS_SPECIFIC) {
            $users = [];

            $query = /** @lang text */ "SELECT user_id FROM silr_selected_users WHERE schedule_id = %s";

            $result = $DIC->database()->queryF($query, ['integer'], [$this->id]);

            while ($record = $DIC->database()->fetchAssoc($result)) {
                $users[] = ["id" => $record['user_id']];
            }

            return ["specific_users" => $users];
        } elseif ($this->users === self::USERS_BY_ROLE) {
            $roles = [];

            $query = /** @lang text */ "SELECT role_id FROM silr_selected_roles WHERE schedule_id = %s";

            $result = $DIC->database()->queryF($query, ['integer'], [$this->id]);

            while ($record = $DIC->database()->fetchAssoc($result)) {
                $roles[] = ["id" => $record['role_id']];
            }

            return ["role" => $roles];
        } elseif ($this->users === self::USERS_ALL_EXCEPT) {
            $excluded_users = [];

            $query = /** @lang text */ "SELECT user_id FROM silr_excluded_users WHERE schedule_id = %s";

            $result = $DIC->database()->queryF($query, ['integer'], [$this->id]);

            while ($record = $DIC->database()->fetchAssoc($result)) {
                $excluded_users[] = ["id" => $record['user_id']];
            }

            return ["excluded_users" => $excluded_users];
        }

        return [];
    }

    public function getUsersDataToDisplay(): array
    {
        $plugin = ilSurResetPlugin::getInstance();
        $users = $this->getUsersData();

        $data = [];

        if ($this->users === self::USERS_ALL) {
            $data[] = $plugin->txt('all_users');
            return $data;
        } elseif ($this->users === self::USERS_ALL_EXCEPT) {
            $data[] = $plugin->txt('all_users_except');
        }

        foreach ($users as $user_type => $user_list) {
            if ($user_type == 'specific_users' || $user_type == 'excluded_users') {
                foreach ($user_list as $user) {
                    $user_id = $user['id'];
                    $name = ilObjUser::_lookupName($user_id);
                    $data[] = $plugin->txt('user') . ": " . $name['firstname'] . " " . $name['lastname'] . " (" . $name['login'] . ")";
                }
            } elseif ($user_type == 'role') {
                foreach ($user_list as $role) {
                    $role_id = $role['id'];
                    $role_name = ilObject::_lookupTitle($role_id);
                    $data[] = $plugin->txt('role') . ": " . $role_name;
                }
            }
        }

        return $data;
    }

    /**
     */
    private function getAffectedUsers(): array
    {
        $affected = [];
        $objects = $this->getObjectsToReset();

        foreach ($objects as $object) {
            $obj_id = ilObject::_lookupObjectId($object['ref_id']);

            if ($this->users === self::USERS_ALL) {
                $affected = array_merge($affected, ilLPMarks::_getAllUserIds($obj_id));
                $affected = array_merge($affected, ilChangeEvent::_getAllUserIds($obj_id));
            } elseif ($this->users === self::USERS_SPECIFIC) {
                foreach ($this->getUsersData()['specific_users'] as $user) {
                    $affected[] = $user['id'];
                }
            } elseif ($this->users === self::USERS_BY_ROLE) {
                global $DIC;

                $user_ids = ilLPMarks::_getAllUserIds($obj_id);
                $user_ids = array_merge($user_ids, ilChangeEvent::_getAllUserIds($obj_id));
                $user_ids_filtered = [];

                foreach ($user_ids as $user_id) {
                    $user_roles = $DIC->rbac()->review()->assignedRoles($user_id);

                    foreach ($this->getUsersData()['role'] as $role) {
                        if (in_array($role['id'], $user_roles)) {
                            $user_ids_filtered[] = $user_id;
                            break;
                        }
                    }
                }

                $affected = array_merge($affected, $user_ids_filtered);
            } elseif ($this->users === self::USERS_ALL_EXCEPT) {
                $user_ids = ilLPMarks::_getAllUserIds($obj_id);
                $user_ids = array_merge($user_ids, ilChangeEvent::_getAllUserIds($obj_id));

                foreach ($this->getUsersData()['excluded_users'] as $excluded_user) {
                    $user_ids = array_filter($user_ids, function ($id) use ($excluded_user) {
                        return $id !== $excluded_user['id'];
                    });
                }

                $affected = array_merge($affected, $user_ids);
            }
        }

        return array_unique($affected);
    }


    /**
     * @throws DateMalformedStringException
     */
    public function shouldRun(): bool
    {
        if ($this->frequency == "manual") {
            return false;
        }

        $last_run = new DateTime($this->last_run ?? $this->created_at);
        $today = new DateTime();
        $frequency_data = json_decode($this->frequency_data, true);

        if (!is_array($frequency_data) || empty($frequency_data)) {
            return false;
        }

        $this->logger->log("Checking if schedule with ID $this->id should run", ilLogLevel::DEBUG);

        switch ($this->frequency) {
            case 'minutely':
                $interval = (int) $frequency_data['interval'];

                if ($today->diff($last_run)->i >= $interval) {
                    return true;
                }

                break;
            case 'hourly':
                $interval = (int) $frequency_data['interval'];

                if ($today->diff($last_run)->h >= $interval) {
                    return true;
                }

                break;
            case 'daily':
                $interval = (int) $frequency_data['interval'];

                if ($today->diff($last_run)->days >= $interval) {
                    return true;
                }

                break;
            case 'weekly':
                $interval = (int) $frequency_data['interval'];

                if ($today->diff($last_run)->days >= $interval * 7) {
                    return true;
                }

                break;
            case 'monthly':
                $interval = (int) $frequency_data['interval'];

                if ($today->diff($last_run)->m >= $interval) {
                    return true;
                }

                break;
            case 'yearly':
                $interval = (int) $frequency_data['interval'];

                if ($today->diff($last_run)->y >= $interval) {
                    return true;
                }

                break;
            case 'day_of_week':
                if ($today->format('Y-m-d') === $last_run->format('Y-m-d')) {
                    return false;
                }

                $today = (int) $today->format('N');
                // The form stores Sunday as 0, while DateTime::format('N') uses 7.
                $day_of_week = (int) $frequency_data['day'] ?: 7;

                if ($today === $day_of_week) {
                    return true;
                }
                break;
            case 'day_of_year':
                if ($today->format('Y-m-d') === $last_run->format('Y-m-d')) {
                    return false;
                }

                $today_month = (int) $today->format('n');
                $today_day = (int) $today->format('j');

                $month = (int) $frequency_data['month'];
                $day = (int) $frequency_data['day'];

                if ($today_month === $month && $today_day === $day) {
                    return true;
                }
                break;
        }

        $this->logger->log("Schedule with ID $this->id should not run at this time", ilLogLevel::DEBUG);

        return false;
    }

    /**
     * @throws DateMalformedStringException
     * @throws DateMalformedIntervalStringException
     * @throws DateInvalidOperationException
     */
    public function shouldNotify(): bool
    {
        if (!$this->email_notifications) {
            return false;
        }

        $last_run = new DateTime($this->last_run ?? $this->created_at);
        $last_notification = new DateTime($this->last_notification ?? '1970-01-01 00:00:00');
        $today = new DateTime();

        // A schedule has at most one advance notification between two resets.
        if ($last_notification >= $last_run) {
            return false;
        }

        if ($today->format('Y-m-d') === $last_notification->format('Y-m-d')) {
            return false;
        }

        $frequency_data = json_decode($this->frequency_data, true);

        if (!is_array($frequency_data) || empty($frequency_data)) {
            return false;
        }

        $next_run = clone $today;

        $this->logger->log("Checking if schedule with ID $this->id should notify", ilLogLevel::DEBUG);

        switch ($this->frequency) {
            case 'minutely':
                $interval = (int) $frequency_data['interval'];
                $next_run = $last_run->add(new DateInterval('PT' . $interval . 'M'));
                break;
            case 'hourly':
                $interval = (int) $frequency_data['interval'];
                $next_run = $last_run->add(new DateInterval('PT' . $interval . 'H'));
                break;
            case 'daily':
                $interval = (int) $frequency_data['interval'];
                $next_run = $last_run->add(new DateInterval('P' . $interval . 'D'));
                break;
            case 'weekly':
                $interval = (int) $frequency_data['interval'];
                $next_run = $last_run->add(new DateInterval('P' . $interval . 'W'));
                break;
            case 'monthly':
                $interval = (int) $frequency_data['interval'];
                $next_run = $last_run->add(new DateInterval('P' . $interval . 'M'));
                break;
            case 'yearly':
                $interval = (int) $frequency_data['interval'];
                $next_run = $last_run->add(new DateInterval('P' . $interval . 'Y'));
                break;
            case 'day_of_week':
                // The form stores Sunday as 0, while DateTime::format('N') uses 7.
                $day_of_week = (int) $frequency_data['day'] ?: 7;
                $next_run = clone $last_run;

                $days_to_add = ($day_of_week - (int) $last_run->format('N') + 7) % 7;
                if ($days_to_add === 0) {
                    $days_to_add = 7;
                }
                $next_run->add(new DateInterval('P' . $days_to_add . 'D'));
                break;
            case 'day_of_year':
                $month = (int) $frequency_data['month'];
                $day = (int) $frequency_data['day'];

                $next_run = new DateTime("{$last_run->format('Y')}-$month-$day");
                if ($next_run <= $last_run) {
                    $next_run->modify('+1 year');
                }
                break;
        }

        $days_in_advance = $this->days_in_advance;
        $next_run_with_advance = clone $next_run;
        $next_run_with_advance->sub(new DateInterval('P' . $days_in_advance . 'D'));

        if ($next_run_with_advance->format('Y-m-d') <= $today->format('Y-m-d')) {
            return true;
        }

        $this->logger->log("Schedule with ID $this->id should not notify at this time", ilLogLevel::DEBUG);

        return false;
    }

    /**
     * @throws ilStudyProgrammeTreeException
     */
    public function run(int $method = self::METHOD_AUTOMATIC): ScheduleExecutionResult
    {
        $result = new ScheduleExecutionResult($this->getId(), $method);

        $this->logger->log("Running schedule with ID $this->id using method " . ($method === self::METHOD_MANUAL ? 'manual' : 'automatic'), ilLogLevel::DEBUG);

        $this->logger->log("Getting objects to reset for schedule with ID $this->id", ilLogLevel::DEBUG);
        $objects = $this->getObjectsToReset();

        if ($this->users === self::USERS_ALL) {
            $this->logger->log("Resetting LP data for all users in schedule with ID $this->id", ilLogLevel::DEBUG);

            foreach ($objects as $object) {
                $obj_id = ilObject::_lookupObjectId($object['ref_id']);
                $lp_obj = ilObjectLP::getInstance($obj_id);

                if ($object['type'] === 'tst') {
                    if ($lp_obj instanceof ilTestLP) {
                        $lp_obj->setTestObject(new ilObjTest($object['ref_id']));
                    }
                } elseif ($object['type'] === 'sahs') {
                    if (ilObjSAHSLearningModule::_lookupSubType($obj_id) == "scorm") {
                        $scorm = new ilObjSCORMLearningModule($object['ref_id']);
                    } elseif (ilObjSAHSLearningModule::_lookupSubType($obj_id) == "scorm2004") {
                        $scorm = new ilObjSCORM2004LearningModule($object['ref_id']);
                    }

                    if (isset($scorm)) {
                        $scorm->deleteTrackingDataOfUsers($this->getAffectedUsers());
                    }
                }

                $lp_obj->resetLPDataForCompleteObject();
            }
        } elseif ($this->users === self::USERS_SPECIFIC) {
            $this->logger->log("Resetting LP data for specific users in schedule with ID $this->id", ilLogLevel::DEBUG);

            foreach ($objects as $object) {
                $obj_id = ilObject::_lookupObjectId($object['ref_id']);
                $lp_obj = ilObjectLP::getInstance($obj_id);
                $user_ids = [];

                foreach ($this->getUsersData()['specific_users'] as $user) {
                    $user_ids[] = $user['id'];
                }

                if ($object['type'] === 'tst') {
                    if ($lp_obj instanceof ilTestLP) {
                        $lp_obj->setTestObject(new ilObjTest($object['ref_id']));
                    }
                } elseif ($object['type'] === 'sahs') {
                    if (ilObjSAHSLearningModule::_lookupSubType($obj_id) == "scorm") {
                        $scorm = new ilObjSCORMLearningModule($object['ref_id']);
                    } elseif (ilObjSAHSLearningModule::_lookupSubType($obj_id) == "scorm2004") {
                        $scorm = new ilObjSCORM2004LearningModule($object['ref_id']);
                    }

                    if (isset($scorm)) {
                        $scorm->deleteTrackingDataOfUsers($user_ids);
                    }
                }

                $lp_obj->resetLPDataForUserIds($user_ids);
            }
        } elseif ($this->users === self::USERS_BY_ROLE) {
            global $DIC;

            $this->logger->log("Resetting LP data for users by role in schedule with ID $this->id", ilLogLevel::DEBUG);

            foreach ($objects as $object) {
                $obj_id = ilObject::_lookupObjectId($object['ref_id']);
                $lp_obj = ilObjectLP::getInstance($obj_id);
                $user_ids = ilLPMarks::_getAllUserIds($obj_id);
                $user_ids =  array_merge($user_ids, ilChangeEvent::_getAllUserIds($obj_id));
                $user_ids_filtered = [];

                foreach ($user_ids as $user_id) {
                    $user_roles = $DIC->rbac()->review()->assignedRoles($user_id);

                    foreach ($this->getUsersData()['role'] as $role) {
                        if (in_array($role['id'], $user_roles)) {
                            $user_ids_filtered[] = $user_id;
                            break;
                        }
                    }
                }

                if ($object['type'] === 'tst') {
                    if ($lp_obj instanceof ilTestLP) {
                        $lp_obj->setTestObject(new ilObjTest($object['ref_id']));
                    }
                } elseif ($object['type'] === 'sahs') {
                    if (ilObjSAHSLearningModule::_lookupSubType($obj_id) == "scorm") {
                        $scorm = new ilObjSCORMLearningModule($object['ref_id']);
                    } elseif (ilObjSAHSLearningModule::_lookupSubType($obj_id) == "scorm2004") {
                        $scorm = new ilObjSCORM2004LearningModule($object['ref_id']);
                    }

                    if (isset($scorm)) {
                        $scorm->deleteTrackingDataOfUsers($user_ids_filtered);
                    }
                }

                $lp_obj->resetLPDataForUserIds($user_ids_filtered);
            }
        } elseif ($this->users === self::USERS_ALL_EXCEPT) {
            $this->logger->log("Resetting LP data for all users except specified in schedule with ID $this->id", ilLogLevel::DEBUG);

            foreach ($objects as $object) {
                $obj_id = ilObject::_lookupObjectId($object['ref_id']);
                $lp_obj = ilObjectLP::getInstance($obj_id);
                $user_ids = ilLPMarks::_getAllUserIds($obj_id);
                $user_ids =  array_merge($user_ids, ilChangeEvent::_getAllUserIds($obj_id));

                foreach ($this->getUsersData()['excluded_users'] as $excluded_user) {
                    $user_ids = array_filter($user_ids, function ($id) use ($excluded_user) {
                        return $id !== $excluded_user['id'];
                    });
                }

                if ($object['type'] === 'tst') {
                    if ($lp_obj instanceof ilTestLP) {
                        $lp_obj->setTestObject(new ilObjTest($object['ref_id']));
                    }
                } elseif ($object['type'] === 'sahs') {
                    if (ilObjSAHSLearningModule::_lookupSubType($obj_id) == "scorm") {
                        $scorm = new ilObjSCORMLearningModule($object['ref_id']);
                    } elseif (ilObjSAHSLearningModule::_lookupSubType($obj_id) == "scorm2004") {
                        $scorm = new ilObjSCORM2004LearningModule($object['ref_id']);
                    }

                    if (isset($scorm)) {
                        $scorm->deleteTrackingDataOfUsers($user_ids);
                    }
                }

                $lp_obj->resetLPDataForUserIds($user_ids);
            }
        }

        $this->logger->log("Schedule with ID $this->id has been executed", ilLogLevel::DEBUG);

        $this->setLastRun(date('Y-m-d H:i:s'));
        $this->save();

        $result->save($this->getAffectedUsers(), $this->getObjectsData());

        return $result;
    }

    /**
     * @throws DateMalformedStringException
     * @throws ilStudyProgrammeTreeException
     * @throws DateMalformedIntervalStringException
     */
    public function notify(): void
    {
        global $DIC;

        $notification_time = date('Y-m-d H:i:s');
        // Claim this reset cycle before sending so concurrent cron processes cannot enqueue duplicates.
        $query = /** @lang text */ "UPDATE silr_schedules
            SET last_notification = %s
            WHERE id = %s
              AND (last_notification IS NULL OR last_notification < last_run)";
        $DIC->database()->manipulateF(
            $query,
            ['timestamp', 'integer'],
            [$notification_time, $this->id]
        );

        if ($DIC->database()->affectedRows() !== 1) {
            $this->logger->log("Notification for schedule with ID $this->id was already claimed", ilLogLevel::DEBUG);
            return;
        }

        $this->setLastNotification($notification_time);
        $this->sendNotification();
    }

    public function getObjectsToReset(): array
    {
        global $DIC;

        $objects = [];
        $processed_refs = [];
        $tree = $DIC->repositoryTree();

        foreach ($this->getObjectsData() as $object) {
            if (!$this->refExist($objects, $object['id'])) {
                $type = ilObject::_lookupType(ilObject::_lookupObjectId($object['id']));

                $objects[] = [
                    "ref_id" => $object['id'],
                    "type" => $type,
                ];

                if (!$tree->isInTree($object['id'])) {
                    continue;
                }

                $this->getChildObjectsRecursive($tree, $object['id'], $objects, $processed_refs);
            }
        }

        return $objects;
    }

    private function getChildObjectsRecursive($tree, $parent_ref_id, &$objects, &$processed_refs): void
    {
        $children = $tree->getChilds($parent_ref_id);

        foreach ($children as $child) {
            if (!isset($processed_refs[$child['ref_id']])) {
                $type = $child['type'] ?? ilObject::_lookupType(ilObject::_lookupObjectId($child['ref_id']));

                if ($type == "crsr") {
                    $course = new ilObjCourseReference($child['ref_id']);

                    $ref_id = $course->getTargetRefId();
                    $type = "crs";
                } else {
                    $ref_id = (int) $child['ref_id'];
                }

                $objects[] = [
                    "ref_id" => $ref_id,
                    "type" => $type
                ];

                $processed_refs[$ref_id] = true;


                $this->getChildObjectsRecursive($tree, $ref_id, $objects, $processed_refs);
            }
        }
    }

    private function refExist(array $objects, int $ref_id): bool
    {
        foreach ($objects as $object) {
            if ($object['ref_id'] === $ref_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws DateMalformedStringException
     * @throws DateMalformedIntervalStringException|ilStudyProgrammeTreeException
     */
    public function sendNotification(?string $subject = null, ?string $text = null): void
    {
        $subject = $subject ?? $this->getNotificationSubject();
        $template = $text ?? $this->getNotificationTemplate();

        if (empty($subject) || empty($template)) {
            return;
        }

        $template = str_replace(
            ['[date]', '[time]'],
            $this->calculateDateAndTime(),
            $template
        );

        $mail = new ilMail(ANONYMOUS_USER_ID);

        $user_ids = $this->getAffectedUsers();

        foreach ($user_ids as $user_id) {
            $user = new ilObjUser($user_id);

            $lng = new ilLanguage($user->getLanguage());
            $lng->loadLanguageModule("ui_uihk_silr");

            $template_for_user = str_replace(
                ['[name]', '[firstname]', '[lastname]', '[login]'],
                [$user->getFullname(), $user->getFirstname(), $user->getLastname(), $user->getLogin()],
                $template
            );

            $mail->enqueue(
                $user->getEmail(),
                "",
                "",
                $subject,
                $template_for_user,
                []
            );

            sleep(10);
        }
    }

    /**
     * @throws DateMalformedStringException
     * @throws DateMalformedIntervalStringException
     */
    private function calculateDateAndTime(): array
    {
        $today = new DateTime();
        $date = $today->format('Y-m-d');
        $time = $today->format('H:i:s');
        $last_run = new DateTime($this->getLastRun() ?? $this->getCreatedAt());

        switch ($this->frequency) {
            case 'minutely':
                $date = $today->format('Y-m-d');
                $time = $today->add(new DateInterval('PT' . (int) $this->getFrequencyData()['interval'] . 'M'))->format('H:i:s');
                break;
            case 'hourly':
                $date = $today->format('Y-m-d');
                $time = $today->add(new DateInterval('PT' . (int) $this->getFrequencyData()['interval'] . 'H'))->format('H:i:s');
                break;
            case 'daily':
                $date = $today->format('Y-m-d');
                $time = $today->add(new DateInterval('P' . (int) $this->getFrequencyData()['interval'] . 'D'))->format('H:i:s');
                break;
            case 'weekly':
                $date = $today->format('Y-m-d');
                $time = $today->add(new DateInterval('P' . (int) $this->getFrequencyData()['interval'] . 'W'))->format('H:i:s');
                break;
            case 'monthly':
                $date = $today->format('Y-m-d');
                $time = $today->add(new DateInterval('P' . (int) $this->getFrequencyData()['interval'] . 'M'))->format('H:i:s');
                break;
            case 'yearly':
                $date = $today->format('Y-m-d');
                $time = $today->add(new DateInterval('P' . (int) $this->getFrequencyData()['interval'] . 'Y'))->format('H:i:s');
                break;
            case 'day_of_week':
                // The form stores Sunday as 0, while DateTime::format('N') uses 7.
                $day_of_week = (int) $this->getFrequencyData()['day'] ?: 7;
                $last_run_day = (int) $last_run->format('N');

                $days_to_add = ($day_of_week - $last_run_day + 7) % 7;
                if ($days_to_add === 0) {
                    $days_to_add = 7;
                }
                $date = $last_run->add(new DateInterval('P' . $days_to_add . 'D'))->format('Y-m-d');
                $time = $last_run->format('H:i:s');
                break;
            case 'day_of_year':
                $month = (int) $this->getFrequencyData()['month'];
                $day = (int) $this->getFrequencyData()['day'];

                $last_run_month = (int) $last_run->format('n');
                $last_run_day = (int) $last_run->format('j');

                if ($last_run_month === $month && $last_run_day === $day) {
                    return [$date, $time];
                }

                $next_date = new DateTime("{$last_run->format('Y')}-$month-$day");
                if ($next_date < $last_run) {
                    $next_date->modify('+1 year');
                }
                $date = $next_date->format('Y-m-d');
                $time = $last_run->format('H:i:s');
                break;
        }

        return [$date, $time];
    }

}
