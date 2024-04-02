<?php

declare(strict_types=1);

namespace Zolinga\Cron\Tests\Unit;

use Zolinga\Cron\CronJob;
use Zolinga\System\Types\StatusEnum;
use PHPUnit\Framework\TestCase;
use Zolinga\System\Events\{RequestEvent, Event, RequestResponseEvent};

/**
 * Class ServiceTest
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-12
 */
class CronServiceTest extends TestCase {
    public function testServiceExistence(): void {
        global $api;

        $this->assertIsObject($api->cron, "The \$api->cron service should exist");
    }

    public function testJobCreation(): void {
        global $api;

        $ev = new RequestEvent("test", RequestEvent::ORIGIN_INTERNAL, ["test" => "data"]);
        $ev->uuid = "test-uuid";

        $api->cron->unschedule($ev->uuid); // remove old job from previous tests if it exists
        $job = $api->cron->schedule($ev, "+1 minute");

        $this->assertIsInt($job->id, "The ID should be an integer");
        $api->cron->unschedule($job->id);
        $job = $api->cron->get($job->id);
        $this->assertFalse($job, "The job should not exist");
    }
}