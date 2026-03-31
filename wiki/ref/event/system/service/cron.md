## Description

Cron job management service. Provides API for scheduling, unscheduling, and querying cron jobs.

- **Service:** `$api->cron`
- **Class:** `Zolinga\Cron\CronService`
- **Module:** zolinga-cron
- **Event:** `system:service:cron`

## Usage

```php
use Zolinga\System\Events\RequestEvent;

$event = new RequestEvent("my:event", RequestEvent::ORIGIN_INTERNAL, ["param" => "value"]);
$job = $api->cron->schedule($event, "12:00", "+1 day");
$api->cron->unschedule($job);
```
