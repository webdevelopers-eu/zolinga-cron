<?php

declare(strict_types=1);

namespace Zolinga\Cron;

use Zolinga\System\Events\{ListenerInterface, Event, RequestEvent, RequestResponseEvent};

/**
 * Cron service
 * 
 * This class is responsible for managing cron jobs.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-13
 */
class CronRunner implements ListenerInterface
{
    private ?int $slot = null;

    private function list(RequestResponseEvent $event): void
    {
        global $api;

        $jobIds = $api->db->query("SELECT id FROM cronJobs ORDER BY start asc")->fetchFirstColumnAll();
        $event->response['jobs'] = array_map(fn ($id) => new CronJob($id), $jobIds);
        $event->setStatus($event::STATUS_OK, "Found " . count($event->response['jobs']) . " cron jobs.");
    }

    private function remove(RequestEvent $event): void
    {
        $job = new CronJob();
        $job->load($event->request['remove']);
        $job->remove();
        $event->setStatus($event::STATUS_OK, "Cron job removed.");
    }

    public function run(RequestEvent|RequestResponseEvent $event): void
    {
        global $api;

        if (isset($event->request['list']) && $event instanceof RequestResponseEvent) {
            $this->list($event);
            return;
        }

        if (isset($event->request['remove'])) {
            $this->remove($event);
            return;
        }

        if (isset($event->request['run']) && $event instanceof RequestResponseEvent) {
            $job = new CronJob();
            $job->load($event->request['run']);
            $this->runJob($job);
            $event->response['job'] = $job;
            $event->setStatus($event::STATUS_OK, "Cron job run.");
            return;
        }

        $this->processJobs($event);
    }

    private function processJobs(RequestEvent $event): void
    {
        global $api;

        if (!$this->claimSlot()) {
            $api->log->info("cron", "{$api->config['cron']['maxConcurrentJobs']} runner(s) is already running. Exitting.");
            $event->setStatus($event::STATUS_LOCKED, "Cron runner is already running.");
            return;
        }

        // Find all cron jobs where start time is in the past
        $jobDataList = $api->db->query("
            SELECT 
                * 
            FROM 
                cronJobs 
            WHERE 
                start <= ? 
                AND 
                (end IS NULL OR end > ?) 
                AND
                errors < ?
            ", time(), time(), $api->config['cron']['maxErrors']);

        foreach ($jobDataList as $jobData) {
            $job = new CronJob($jobData);
            if ($this->claimJob($job)) {
                try {
                    $this->runJob($job);
                } catch (\Throwable $e) {
                    $api->log->error("cron", "Error running cron job {$job}: {$e->getMessage()}");
                } finally {
                    $this->releaseJob($job);
                }
            }
        }

        $this->releaseSlot();
        $event->setStatus($event::STATUS_OK, "Cron runner finished.");
    }

    /**
     * Claim a job for processing. Will return false if the job is already claimed.
     *
     * @param CronJob $job
     * @return boolean
     */
    private function claimJob(CronJob $job): bool
    {
        global $api;
        return (bool) $api->registry->acquireLock("cron:job:{$job->id}");
    }

    /**
     * Release a job after processing.
     *
     * @param CronJob $job
     * @return void
     */
    private function releaseJob(CronJob $job): void
    {
        global $api;
        $api->registry->releaseLock("cron:job:{$job->id}");
    }

    /**
     * Honor $api->config['cron']['maxConcurrentJobs'] and claim a slot for processing.
     * 
     * If there is too many other runners running, this method will return false.
     *
     * @return boolean
     */
    private function claimSlot(): bool
    {
        global $api;

        $slots = (int) $api->config['cron']['maxConcurrentJobs'] ?: 3;

        for ($slot = 0; $slot < $slots; $slot++) {
            if ($api->registry->acquireLock("cron:slot:$slot")) {
                $this->slot = $slot;
                return true;
            }
        }

        return false;
    }

    /**
     * Run a cron job and process the result by rescheduling the job or removing it from the queue.
     *
     * @param CronJob $job
     * @return void
     */
    private function runJob(CronJob $job): void
    {
        global $api;

        $event = new RequestEvent($job->event, RequestEvent::ORIGIN_INTERNAL, $job->request);
        $event->uuid = $job->uuid;

        $job->lastRun = time();
        $job->status = $job::STATUS_PROCESSING;
        $job->message = "Processing job (pid " . getmypid() . ")";
        $job->save();

        $api->log->info("cron", "Running cron event {$event} triggered by {$job}.", ['job' => $job, 'pid' => getmypid(), 'event' => $event]);
        $event->dispatch();
        // Idiotic intelephense does not support $api->log() using __invoke() and stubbornly insists on not supporting suppressing of errors either.
        $api->log->log(
            $event->status->isOk() ? $api->log::SEVERITY_INFO : $api->log::SEVERITY_ERROR,
            "cron",
            "Cron event {$event} triggered by {$job} finished and is not OK: {$event->status->value} {$event->message}"
        );

        $job->status = $event->status;
        $job->message = $event->message;
        $job->totalRuns++;
        $job->errors = $event->status->isOk() ? 0 : $job->errors + 1;

        $this->rescheduleOrRemove($job);
    }

    private function rescheduleOrRemove(CronJob $job): void
    {
        global $api;

        if ($job->status->isOk() && $job->status !== $job::STATUS_CONTINUE && ($job->recurring === null || $job->end <= time())) {
            // Done and is not recurring - remove
            $api->log->info("cron", "{$job} finished. Removing from queue.");
            $job->remove();
        } elseif ($job->status->isError() || $job->status == $job::STATUS_CONTINUE || $job->status == $job::STATUS_UNDETERMINED) {
            // Run soon
            $job->start = time() + 60; // some delay so long-running jobs do not block others
            $api->log->info("cron", "{$job} finished. Rescheduling to run soon on " . date('c', $job->start) . ".");
            $job->save();
        } elseif ($job->errors >= $api->config['cron']['maxErrors']) {
            // Too many errors - remove
            $api->log->error("cron", "{$job} finished. Too many errors ({$job->errors}). Removing from queue.", ['job' => $job]);
            $job->remove();
        } else {
            // Reschedule for next run
            $job->start = intval(strtotime($job->recurring, $job->start))
                or throw new \Exception("Invalid recurring value: {$job->recurring}");
            $api->log->info("cron", "{$job} finished. Rescheduling to run on " . date('c', $job->start) . " ({$job->recurring})");
            $job->save();
        }
    }

    /**
     * Release the Cron Runner slot after processing.
     *
     * @return void
     */
    private function releaseSlot(): void
    {
        global $api;

        if ($this->slot !== null) {
            $api->registry->releaseLock("cron:slot:{$this->slot}");
        }
    }
}
