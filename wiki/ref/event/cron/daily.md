## Description

A recurring cron event scheduled to fire once per day. Modules register listeners for daily maintenance tasks.

- **Event:** `cron:daily`
- **Origin:** `cli` (dispatched internally by the Cron Runner from the `cronJobs` table)
- **Event Type:** `\Zolinga\System\Events\RequestEvent`

## Listeners

| Module | Class | Method | Description |
|---|---|---|---|
| ipdefender | `Ipd\App\Sitemap\SitemapCronJob` | `onGenerateSitemap` | Regenerates the sitemap |
| ipdefender | `Ipd\App\Product\ProductsCronJob` | `onGenerateProductFeed` | Generates Google Merchant product feed |

## How It Works

The `cron:daily` event is a scheduled cron job stored in the `cronJobs` database table with a daily recurrence. When `bin/zolinga cron` runs (typically from system crontab), the Cron Runner picks up due jobs and dispatches them as internal events.

## See Also

- [cron](../cron.md) — the main cron CLI command
