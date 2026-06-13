<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Upcast;

use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastMiddleware;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Tests\Unit\Extension\Upcast\Fixture\UpcastFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpcastMiddleware::class)]
final class UpcastMiddlewareTest extends TestCase
{
    public function testHydrate(): void
    {
        $middleware = new UpcastMiddleware([
            CallbackUpcaster::forClass(
                UpcastFixture::class,
                /**
                 * @param array{name: string}|array{firstName: string, lastName: string} $data
                 *
                 * @return array{name: string}
                 */
                static function (array $data): array {
                    if (isset($data['name'])) {
                        return $data;
                    }

                    $data['name'] = $data['firstName'] . ' ' . $data['lastName'];

                    return $data;
                },
            ),
        ]);

        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);

        $expectedObject = new UpcastFixture('Jane Doe');

        $nextMiddleware = $this->createMock(Middleware::class);
        $nextMiddleware->expects(self::once())
            ->method('hydrate')
            ->with($metadata, ['firstName' => 'Jane', 'lastName' => 'Doe', 'name' => 'Jane Doe'], [], self::isInstanceOf(Stack::class))
            ->willReturn($expectedObject);

        $stack = new Stack([$nextMiddleware]);

        $object = $middleware->hydrate($metadata, ['firstName' => 'Jane', 'lastName' => 'Doe'], [], $stack);

        self::assertSame($expectedObject, $object);
    }

    public function testExtract(): void
    {
        $middleware = new UpcastMiddleware([]);
        $metadata = (new AttributeMetadataFactory())->metadata(UpcastFixture::class);
        $object = new UpcastFixture('Jane Doe');

        $nextMiddleware = $this->createMock(Middleware::class);
        $nextMiddleware->expects(self::once())
            ->method('extract')
            ->with($metadata, $object, [], self::isInstanceOf(Stack::class))
            ->willReturn(['name' => 'Jane Doe']);

        $stack = new Stack([$nextMiddleware]);

        self::assertSame(['name' => 'Jane Doe'], $middleware->extract($metadata, $object, [], $stack));
    }
}
