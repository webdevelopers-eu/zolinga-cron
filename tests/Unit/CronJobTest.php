<?php

declare(strict_types=1);

namespace Zolinga\Cron\Tests\Unit;

use Zolinga\Cron\CronJob;
use Zolinga\System\Types\StatusEnum;
use PHPUnit\Framework\TestCase;

/**
 * Class ServiceTest
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-12
 */
class CronJobTest extends TestCase
{
    public function testJobParams():void {
        $stamp = time();

        $job = new CronJob([
            "id" => 1,
            "event" => "test",
            "start" => $stamp,
            "errors" => 0,
            "status" => StatusEnum::OK,
            "recurring" => null
        ]);

        $this->assertEquals(1, $job->id, "ID should be 1");
        $this->assertEquals("test", $job->event, "Event should be 'test'");
        $this->assertEquals($stamp, $job->start, "Start should $stamp");
        $this->assertEquals(0, $job->errors, "Errors should be 0");
        $this->assertEquals(StatusEnum::OK, $job->status, "Status should be OK");
        $this->assertNull($job->recurring, "Recurring should be null");

        // Test more status values to StatusEnum conversions
        $job->status = "error";
        $this->assertEquals(StatusEnum::ERROR, $job->status, "Status should be ERROR");

        $job->status = "Not Found";
        $this->assertEquals(StatusEnum::NOT_FOUND, $job->status, "Status should be NOT_FOUND");

        $job->status = 404;
        $this->assertEquals(StatusEnum::NOT_FOUND, $job->status, "Status should be NOT_FOUND");

        // Set timestamps
        $job->lastRun = $stamp;
        $this->assertEquals($stamp, $job->lastRun, "Last run should be $stamp");

        $job->end = null;
        $this->assertNull($job->end, "End should be null");

        $job->end = $stamp;
        $this->assertEquals($stamp, $job->end, "End should be $stamp");

        $job->start = "+10 seconds";
        $this->assertIsInt($job->start, "Start should be an integer");
        $this->assertGreaterThan($stamp, $job->start, "Start should be in the future");
    }

    public function testSavingJobs(): void {
        $oldJob = new CronJob;
        if ($oldJob->load("test")) {
            $oldJob->remove();
        }

        $job = new CronJob([
            "uuid" => "test",
            "event" => "test",
            "start" => "+10 minute",
        ]);
        $job->create();
        $this->assertIsInt($job->id, "ID should be an integer");

        $job->status = StatusEnum::ERROR;
        $job->save();
        $this->assertEquals(StatusEnum::ERROR, $job->status, "Status should be ERROR");
        $job->remove();
    }
}