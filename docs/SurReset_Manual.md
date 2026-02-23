# SurReset Technical Manual

**Plugin:** SurReset  
**Type:** UIComponent / UserInterfaceHook plugin  
**Target audience:** ILIAS administrators and technical staff  

## 1. Introduction

SurReset is an ILIAS plugin that allows administrators to reset learning progress for selected repository objects using configurable schedules.

Main capabilities:
- Create named reset schedules.
- Select reset scope by objects (courses/programs and children in repository tree).
- Select affected users (all, specific users, users by role, all except selected users).
- Run schedules manually from plugin administration.
- Run schedules automatically through ILIAS cron.
- Send optional notification emails before scheduled resets.
- Review execution history with date, method, affected users, and execution duration.

## 2. System Requirements and Compatibility

- **ILIAS version:** ILIAS 7
- **PHP version:** PHP 7.3 or higher.
- **Activation context:** ILIAS Plugin Administration
- **Execution dependencies:**
  - ILIAS setup update completed (`php setup/setup.php update`)
  - ILIAS cron enabled for automatic schedules/notifications

## 3. Accessing Plugin Configuration

In ILIAS Administration:
1. Go to **Administration > Plugins**.
2. Locate **SurReset** in UIComponent/UserInterfaceHook plugins.
3. Open plugin actions/configuration.

### Screenshot
![Plugin activation in ILIAS administration](screenshots/01-plugin-activation.png)

## 4. Plugin Activation and Deactivation

1. In plugin administration, click **Activate** to enable SurReset.
2. Confirm plugin status is active.
3. To disable it, click **Deactivate**.

## 5. Schedule Management

### 5.1 Creating a Schedule

1. Open SurReset configuration.
2. Select **New schedule**.
3. Fill required fields:
- **Name**
- **Objects** to reset
- **Users** scope
- **Frequency** (manual or automatic)
- Optional **notifications** (subject/template, days in advance)
4. Save.

### 5.2 Editing a Schedule

1. Go to **List** tab.
2. Select schedule action **Edit**.
3. Update fields and save.

### Screenshots
![Schedules list](screenshots/02-schedules-list.png)
![Schedule create or edit form - part 1](screenshots/schedule-form_1.png)
![Schedule create or edit form - part 2](screenshots/schedule-form_2.png)

## 6. Manual Schedule Execution

1. In **List**, choose **Run** for a schedule.
2. Confirm execution.
3. Optionally provide a manual notification subject/message before running.
4. Review success/failure message after execution.

### Screenshot
![Manual schedule execution confirmation](screenshots/04-manual-run.png)

## 7. Automatic Execution (Cron)

SurReset provides an internal cron job (`sur_reset`) that:
- Evaluates non-manual schedules.
- Runs schedules when `shouldRun()` conditions are met.
- Sends notifications when `shouldNotify()` conditions are met.

### 7.1 Cron Requirements

- ILIAS cron must be enabled and running periodically.
- SurReset cron job must be active in cron administration.

### 7.2 Default Cron Behavior

- Auto activation: enabled (`hasAutoActivation(): true`)
- Flexible schedule: enabled
- Default type: daily
- Default value: 1

### 7.3 When Schedules Trigger

Trigger depends on each schedule frequency configuration:
- `minutely`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`
- `day_of_week`
- `day_of_year`

## 8. Verifying Results

Use plugin **Execution History** to confirm operations:
1. Open **History** tab.
2. Verify entries by date, method, affected users, and duration.
3. Open **View** action to inspect details:
- Affected objects
- Affected users
- Execution metadata

### Screenshot
![Execution history and details](screenshots/05-execution-history.png)

## 9. Logs and Debugging

SurReset writes operational logs under logger channel:
- `silr.schedule`

Recommended checks:
1. ILIAS logs after save/run/cron cycles.
2. Cron run status and error messages.
3. Execution history entries in plugin UI.

Technical references:
- Cron implementation: `classes/class.ilSurResetCron.php`
- Schedule engine: `classes/objects/class.Schedule.php`
- History persistence: `classes/objects/class.ScheduleExecutionResult.php`
- DB schema: `sql/dbupdate.php`

## 10. Database Objects (Reference)

Main tables created by plugin:
- `silr_schedules`
- `silr_selected_objects`
- `silr_selected_users`
- `silr_excluded_users`
- `silr_selected_roles`
- `silr_history`
- `silr_users_affected`
- `silr_objects_affected`

## 11. Troubleshooting

### Issue: Schedule does not run automatically
- Confirm ILIAS cron is enabled and executing.
- Confirm schedule frequency is automatic (not manual).
- Confirm schedule data is saved correctly.

### Issue: No notification email received
- Confirm email notifications are enabled in the schedule.
- Confirm subject/template are configured.
- Confirm user emails are valid.
- Confirm cron is running.

### Issue: No history entry after expected run
- Check manual run from UI to validate base flow.
- Check cron execution result/status in ILIAS.
- Check logs for schedule evaluation and run errors.
