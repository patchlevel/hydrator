<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle;

use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle\Fixture\LifecycleFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LifecycleExtension::class)]
final class LifecycleExtensionTest extends TestCase
{
    public function testIntegration(): void
    {
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new LifecycleExtension())
            ->build();

        $data = ['name' => 'foo'];
        $object = $hydrator->hydrate(LifecycleFixture::class, $data);

        self::assertSame('foo [preHydrate] [postHydrate]', $object->name);

        $extractedData = $hydrator->extract($object);

        self::assertIsArray($extractedData);
        self::assertSame('foo [preHydrate] [postHydrate] [preExtract] [postExtract]', $extractedData['name']);
    }
}
