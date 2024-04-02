Priority: 0.6

# Cron Event

This is the `cli` (command line) event that is triggered by the `bin/zolinga cron` command. The command runs the cron jobs that are due to run at the time of the command execution.

The command is typically run by the system's cron daemon at regular intervals, e.g. every minute, and it checks if any cron jobs are due to run at the time of the command execution. If there are any, it runs them.

## Syntax

```bash
bin/zolinga cron [--run=<job>] [--list] [--remove=<job>]
```

Following command will execute all the tasks that are due to be run.

```bash
bin/zolinga cron
```


# Related
{{Cron Related}}
