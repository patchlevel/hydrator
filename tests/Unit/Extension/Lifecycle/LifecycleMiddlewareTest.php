<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle;

use Patchlevel\Hydrator\Extension\Lifecycle\Lifecycle;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleMiddleware;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LifecycleFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

use function assert;
use function is_string;

#[CoversClass(LifecycleMiddleware::class)]
final class LifecycleMiddlewareTest extends TestCase
{
    public function testHydrate(): void
    {
        $middleware = new LifecycleMiddleware();
        $metadata = $this->metadata(LifecycleFixture::class);
        $metadata->extras[Lifecycle::class] = new Lifecycle(
            preHydrate: 'preHydrate',
            postHydrate: 'postHydrate',
        );

        $innerMiddleware = new class implements Middleware {
            /**
             * @param ClassMetadata<T>     $metadata
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             *
             * @return T
             *
             * @template T of object
             */
            public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
            {
                $name = $data['name'] ?? '';
                assert(is_string($name));

                $object = new LifecycleFixture($name);

                assert($object instanceof $metadata->className);

                return $object;
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
            {
                return [];
            }
        };

        $stack = new Stack([$innerMiddleware]);

        $object = $middleware->hydrate($metadata, ['name' => 'foo'], [], $stack);

        self::assertInstanceOf(LifecycleFixture::class, $object);
        self::assertSame('foo [preHydrate] [postHydrate]', $object->name);
    }

    public function testExtract(): void
    {
        $middleware = new LifecycleMiddleware();
        $metadata = $this->metadata(LifecycleFixture::class);
        $metadata->extras[Lifecycle::class] = new Lifecycle(
            preExtract: 'preExtract',
            postExtract: 'postExtract',
        );

        $innerMiddleware = new class implements Middleware {
            /**
             * @param ClassMetadata<T>     $metadata
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             *
             * @return T
             *
             * @template T of object
             */
            public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
            {
                $object = new stdClass();

                assert($object instanceof $metadata->className);

                return $object;
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
            {
                if ($object instanceof LifecycleFixture) {
                    return ['name' => $object->name];
                }

                return [];
            }
        };

        $stack = new Stack([$innerMiddleware]);
        $object = new LifecycleFixture('foo');

        $data = $middleware->extract($metadata, $object, [], $stack);

        self::assertSame('foo [preExtract] [postExtract]', $data['name']);
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        return (new AttributeMetadataFactory())->metadata($class);
    }
}
