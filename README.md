# SurReset Plugin for ILIAS 7

SurReset is an ILIAS plugin developed by SURLABS.
It allows administrators to reset learning progress for selected repository objects through configurable schedules.

## What SurReset does

- Create reset schedules with a custom name
- Select target objects (courses/programs)
- Choose affected users:
  - All users
  - Specific users
  - Users by role
  - All users except selected users
- Run schedules manually from the plugin UI
- Run schedules automatically with cron
- Send optional email notifications before automatic runs
- Review execution history (date, method, affected users, duration)

## Installation

### Step 1. Install from Git

```bash
# Go to the ILIAS UIHook plugin directory
cd /path/to/ilias/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/

# Clone plugin repository
git clone https://github.com/surlabs/SurResetForILIAS.git SurReset

# Switch to ILIAS 7 branch
cd SurReset
git checkout ilias7
```

### Step 2. Update ILIAS

```bash
# Run from ILIAS root directory
composer du
php setup/setup.php update
```

## Usage

1. Activate **SurReset** in ILIAS Plugin Administration.
2. Open plugin settings and create a new schedule.
3. Choose objects, users, and frequency (manual or automatic).
4. Save and run manually, or wait for cron execution.
5. Check the **Execution History** tab for results.

## Cron

For automatic schedules and notification checks, the ILIAS cron must be enabled and running.
Without cron, only manual runs are executed.

## Compatibility

- Compatible with supported ILIAS releases for this plugin line.
