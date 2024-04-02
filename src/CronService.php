<?php

declare(strict_types=1);

namespace Zolinga\Cron;

use Zolinga\System\Events\{ServiceInterface, RequestEvent};

/**
 * Cron service
 * 
 * This class is responsible for managing cron jobs.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-13
 */
class CronService implements ServiceInterface
{

    /**
     * Schedule a job to be executed at a specific time.
     * 
     * Example:
     * 
     * use Zolinga\System\Events\RequestEvent;
     * 
     * $event = new RequestEvent("test", RequestEvent::ORIGIN_INTERNAL, ["detail" => "data"]);
     * $event->uuid = "test-uuid"; // optional, unique ID to identify the Job by
     * 
     * $job = $api->cron->schedule($event, "12:00", "+1 day", "2024-12-31");
     * echo "Job scheduled with ID: " . $job->id;
     * $api->cron->unschedule("test-uuid");
     * 
     * You can reference the job by its UUID or ID.
     * 
     * $job = $api->cron->get("test-uuid");
     * $job = $api->cron->get(123);
     *
     * @throws \InvalidArgumentException If the event is not internal or if the Event's UUID is not unique.
     * @param RequestEvent $event
     * @param integer|string $start UNIX timestamp or strtotime() string to specify the start time.
     * @param string|null $recurring strtotime() string to specify the recurring period. If null the job will not be repeated.
     * @param null|integer|string $end UNIX timestamp or strtotime() string. If $recuring is set, this is the end of the recurring period. If null the job will be repeated according to $recurring indefinitely. 
     * @return CronJob new created cronjob
     */
    public function schedule(RequestEvent $event, int|string $start, ?string $recurring = null, null|int|string $end = null): CronJob
    {
        if ($event->origin !== RequestEvent::ORIGIN_INTERNAL) {
            throw new \InvalidArgumentException("Only internal events can be scheduled.");
        }

        $job = new CronJob([
            'uuid' => $event->uuid,
            'event' => $event->type,
            'request' => $event->request,
            'start' => $start,
            'recurring' => $recurring,
            'end' => $end
        ]);
        $job->create();

        return $job;
    }

    /**
     * Remove a job from the schedule and database.
     *
     * @param integer|string|CronJob $job The job numeric ID, UUID or the Job object.
     * @return void
     */
    public function unschedule(int|string|CronJob $job): void
    {
        if (is_string($job) || is_int($job)) {
            $jobObj = new CronJob;
            $jobObj->load($job);
        } else {
            $jobObj = $job;
        }

        if ($jobObj->id) {
            $jobObj->remove();
        }
    }

    public function get(int|string $uuidOrId): CronJob|false
    {
        $jobObj = new CronJob;
        return $jobObj->load($uuidOrId) ? $jobObj : false;
    }
}
