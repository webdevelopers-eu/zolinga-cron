# Zolinga Cron

The Cron module is a simple module that allows you to schedule tasks to be executed at regular intervals. It is based on the Unix cron daemon, and is useful for running periodic tasks such as sending out newsletters, performing backups, or other system maintenance tasks.

# Installation

You need to call the `bin/zolinga cron` command in regular intervals. When you call this command, the Cron Service will check if there are any tasks that need to be executed and will run them if necessary.

The ideal place is to run the `bin/zolinga cron` command is in a cron job. You can set up a cron job to run the command at regular intervals, such as every minute or so.

Example of Linux cron line:

```bash
* * * * * /path/to/zolinga/bin/zolinga cron >> /var/log/zolinga-cron.log 2>&1
```

# Usage

The cron listens to the "cron" event from `internal` and `cli` origin. Therefore you can call it from the CLI or from within your application.

There is also `$api->cron` service available to PHP.

## CLI

You can call the cron from the CLI by running the following command:

```bash
bin/zolinga cron [--run=<job>] [--list] [--remove=<job>]
```

If you run the command without any options, it will execute all the tasks that are due to be run.

If you want to execute a specific task, you can use the `--run` option to specify the name of the task.

## PHP

```php
use Zolinga\System\Events\RequestEvent;

$myEvent = new RequestEvent(
    "my-event", 
    RequestEvent::ORIGIN_INTERNAL, 
    ["param1" => "value1"]
);

$api->cron->schedule(
    $myEvent, 
    start: "tomorrow 12:00", 
    recurring: "+1 day", 
    end: "1st of next month"
);
```

At specified time or at nearest possible time, the event will be dispatched. If the event is recurring, it will be rescheduled for the next time.

Your code listening to the event `my-event` will be called. If you set the status "CONTINUE" in the event, the event will be run again very soon. If the event's status is "OK", the event will be 
scheduled for the next time based on the "recurring" parameter.

Example of the event listener:

```php
namespace Test\Example;
use Zolinga\System\Events\RequestEvent;

class MyEventListener {
    public function onMyEvent(RequestEvent $event) {
        // Your code here
        if ($allBatchesFinished) {
           $event->setStatus($event::STATUS_OK, "Event was processed successfully");
        } else {
           $event->setStatus($event::STATUS_CONTINUE, "We need to run a little bit longer");
        }
    }
}
```

Using "CONTINUE" status is useful for long running tasks. The Cron Service will not run the event again immediately, but will wait a little bit before running it again.

# Identifying the Cron Jobs

When you schedule the event using `$api->cron->schedule()` method, you will get a `\Zolinga\Cron\CronJob` object. You can use this object to identify the job and to remove it from the cron.

When calling `$api->cron->unschedule(int|string|CronJob $job)` or `$api->cron->get(int|string $job)` you can use either CronJob ID ($job->id), or `\Zolinga\System\Events\RequestEvent`'s uuid or the CronJob object itself.

When scheduling the event you can set arbitrary uuid on the Event and then you can use it to identify the job.

```php
$myEvent = new RequestEvent(
    "my-event", 
    RequestEvent::ORIGIN_INTERNAL, 
    ["param1" => "value1"]
);

$myEvent->uuid = "my-unique-identifier";

$job = $api->cron->schedule(
    $myEvent, 
    start: "tomorrow 12:00", 
    recurring: "+1 day", 
    end: "1st of next month"
);

$jobId = $job->id;
// ... 

// Later in the code: you can use either $jobId or UUID 
// to identify the job and retrieve or remove it from the cron
$job = $api->cron->get("my-unique-identifier");
$api->cron->unschedule("my-unique-identifier"); // $jobId or $job object would work as well
```

Note that the uuid must be unique and only one scheduled job can have the given uuid.

# Related
{{Cron Related}}
