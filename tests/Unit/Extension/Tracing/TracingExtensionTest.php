<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Tracing;

use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Tracing\TracingDecorator;
use Patchlevel\Hydrator\Extension\Tracing\TracingExtension;
use Patchlevel\Hydrator\Extension\Tracing\TracingHydrator;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\HydratorDecorator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Tracing\Fixture\RecordingTracer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWrapper;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

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

        self::assertSame(
            ['hydrate ' . ProfileCreated::class, 'extract ' . ProfileCreated::class],
            $tracer->traces,
        );
    }

    public function testNestedObjectsAreTraced(): void
    {
        $tracer = new RecordingTracer();

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new TracingExtension($tracer))
            ->buildHydrator();

        $data = ['event' => ['profileId' => '1', 'email' => 'info@patchlevel.de']];
        $wrapper = $hydrator->hydrate(ProfileCreatedWrapper::class, $data);

        self::assertEquals(
            new ProfileCreatedWrapper(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'))),
            $wrapper,
        );
        self::assertSame($data, $hydrator->extract($wrapper));

        self::assertSame(
            [
                'hydrate ' . ProfileCreatedWrapper::class,
                'hydrate ' . ProfileCreated::class,
                'extract ' . ProfileCreatedWrapper::class,
                'extract ' . ProfileCreated::class,
            ],
            $tracer->traces,
        );
    }

    public function testWrapsDecoratorsWithDefaultPriority(): void
    {
        $inner = $this->createMock(Hydrator::class);

        $decorator = $this->createMock(HydratorDecorator::class);
        $decorator->method('decorate')->willReturn($inner);

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new TracingExtension(new RecordingTracer()))
            ->addDecorator($decorator)
            ->buildHydrator();

        self::assertInstanceOf(TracingHydrator::class, $hydrator);

        $reflection = new ReflectionProperty(TracingHydrator::class, 'hydrator');
        self::assertSame($inner, $reflection->getValue($hydrator));
    }
}
