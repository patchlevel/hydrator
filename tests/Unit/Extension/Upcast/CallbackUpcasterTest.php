<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Upcast;

use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle\Fixture\LifecycleFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Upcast\Fixture\UpcastFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;
use function is_string;

#[CoversClass(CallbackUpcaster::class)]
final class CallbackUpcasterTest extends TestCase
{
    public function testUpcast(): void
    {
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);
        $upcaster = CallbackUpcaster::forClass(
            UpcastFixture::class,
            static function (array $data, array $context): array {
                $prefix = $context['prefix'] ?? '';
                $name = $data['name'] ?? '';
                assert(is_string($prefix));
                assert(is_string($name));

                $data['name'] = $prefix . $name;

                return $data;
            },
        );

        self::assertSame(
            ['name' => 'Upcast: foo'],
            $upcaster->upcast($metadata, ['name' => 'foo'], ['prefix' => 'Upcast: ']),
        );
    }

    public function testSkipDifferentClass(): void
    {
        $metadata = (new AttributeMetadataFactory())->metadata(LifecycleFixture::class);
        $upcaster = CallbackUpcaster::forClass(
            UpcastFixture::class,
            static fn (array $data): array => ['name' => 'changed'],
        );

        self::assertSame(['name' => 'foo'], $upcaster->upcast($metadata, ['name' => 'foo'], []));
    }
}
