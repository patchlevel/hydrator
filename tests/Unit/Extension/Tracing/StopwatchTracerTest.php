<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Tracing;

use Patchlevel\Hydrator\Extension\Tracing\Operation;
use Patchlevel\Hydrator\Extension\Tracing\StopwatchTracer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Stopwatch\Stopwatch;

#[CoversClass(StopwatchTracer::class)]
final class StopwatchTracerTest extends TestCase
{
    public function testTrace(): void
    {
        $stopwatch = new Stopwatch();
        $tracer = new StopwatchTracer($stopwatch);

        $object = new stdClass();
        $result = $tracer->trace(Operation::Hydrate, ProfileCreated::class, static fn (): object => $object);

        self::assertSame($object, $result);

        $event = $stopwatch->getEvent('hydrate ' . ProfileCreated::class);
        self::assertSame('hydrator', $event->getCategory());
        self::assertFalse($event->isStarted());
        self::assertCount(1, $event->getPeriods());
    }

    public function testEventIsStoppedOnException(): void
    {
        $stopwatch = new Stopwatch();
        $tracer = new StopwatchTracer($stopwatch, 'custom');

        $exception = null;

        try {
            $tracer->trace(Operation::Extract, ProfileCreated::class, static function (): string {
                throw new RuntimeException('failed');
            });
        } catch (RuntimeException $e) {
            $exception = $e;
        }

        self::assertSame('failed', $exception->getMessage());

        $event = $stopwatch->getEvent('extract ' . ProfileCreated::class);
        self::assertSame('custom', $event->getCategory());
        self::assertFalse($event->isStarted());
    }
}
