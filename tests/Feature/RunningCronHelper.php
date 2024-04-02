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
class RunningCronHelper implements ListenerInterface
{
    public static ?RequestEvent $cronRunEvent = null;
    public static bool $initialized = false;

    static function initListener(): void
    {
        global $api;

        if (!self::$initialized) {
            $api->manifest->addListener(new ListenAtom([
                'event' => 'unitTest:test1',
                'class' => self::class,
                'method' => 'onCronJob',
                'priority' => 0.5,
                'origin' => ['internal']
            ]));
            self::$initialized = true;
        }
    }

    public function onCronJob(RequestEvent $event): void
    {
        self::$cronRunEvent = $event;
        $event->setStatus(StatusEnum::OK, "Cron tested successfully");
    }
}
