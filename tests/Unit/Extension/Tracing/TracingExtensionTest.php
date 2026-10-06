<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Tracing;

use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Tracing\TracingDecorator;
use Patchlevel\Hydrator\Extension\Tracing\TracingExtension;
use Patchlevel\Hydrator\Extension\Tracing\TracingHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Tracing\Fixture\RecordingTracer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TracingExtension::class)]
#[CoversClass(TracingDecorator::class)]
#[CoversClass(TracingHydrator::class)]
final class TracingExtensionTest extends TestCase
{
    public function testIntegration(): void
    {
        $tracer = new RecordingTracer();

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new TracingExtension($tracer))
            ->buildHydrator();

        self::assertInstanceOf(TracingHydrator::class, $hydrator);

        $data = ['profileId' => '1', 'email' => 'info@patchlevel.de'];
        $event = $hydrator->hydrate(ProfileCreated::class, $data);

        self::assertEquals(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')), $event);
        self::assertSame($data, $hydrator->extract($event));

        // only the calls on the hydrator itself are traced
        self::assertSame(
            ['hydrate ' . ProfileCreated::class, 'extract ' . ProfileCreated::class],
            $tracer->traces,
        );
    }
}
