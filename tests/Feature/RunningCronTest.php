<?php

declare(strict_types=1);

namespace Zolinga\Cron\Tests\Feature;

use Zolinga\Cron\CronJob;
use Zolinga\System\Types\StatusEnum;
use PHPUnit\Framework\TestCase;
use Zolinga\System\Events\RequestEvent;
use Zolinga\System\Config\Atom\ListenAtom;
use Zolinga\System\Events\ListenerInterface;

/**
 * Class ServiceTest
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-12
 */
class RunningCronTest extends TestCase
{
    public function testRunner(): void
    {
        global $api;

        $uuid = "test-uuid:".basename(__FILE__);
        RunningCronHelper::initListener();

        $schEvent = new RequestEvent('unitTest:test1', RequestEvent::ORIGIN_INTERNAL, ['hello' => 'world']);
        $schEvent->uuid = $uuid;
        $api->cron->unschedule($uuid);
        $api->cron->schedule($schEvent, time() - 1);

        $runEvent = new RequestEvent('cron', RequestEvent::ORIGIN_CLI);
        $runEvent->dispatch();

        // Zolinga\Cron\Tests\Feature\RunningCronHelper
        $this->assertNotNull(RunningCronHelper::$cronRunEvent, "Cron runner did not run or dynamic listeners are not working.");
        $this->assertSame($schEvent->uuid, RunningCronHelper::$cronRunEvent->uuid, "Cron runner did not run the correct event.");
        $this->assertSame($schEvent->request, RunningCronHelper::$cronRunEvent->request, "The requests must be the same.");
    }
}
