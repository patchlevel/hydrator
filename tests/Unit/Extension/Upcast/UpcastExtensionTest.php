<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Upcast;

use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyMiddleware;
use Patchlevel\Hydrator\Extension\Cryptography\LegacyCryptographyDecryptMiddleware;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\Extension\Upcast\UpcastMiddleware;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Upcast\Fixture\UpcastFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;
use function is_string;

#[CoversClass(UpcastExtension::class)]
final class UpcastExtensionTest extends TestCase
{
    public function testIntegration(): void
    {
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new UpcastExtension(
                beforeTransform: [
                    CallbackUpcaster::forClass(
                        UpcastFixture::class,
                        static function (array $data): array {
                            $firstName = $data['firstName'] ?? '';
                            $lastName = $data['lastName'] ?? '';
                            assert(is_string($firstName));
                            assert(is_string($lastName));

                            $data['name'] = $firstName . ' ' . $lastName;

                            return $data;
                        },
                    ),
                ],
            ))
            ->build();

        $object = $hydrator->hydrate(UpcastFixture::class, ['firstName' => 'Jane', 'lastName' => 'Doe']);

        self::assertSame('Jane Doe', $object->name);
    }

    public function testConfigureAroundCryptography(): void
    {
        $beforeEncodingUpcaster = CallbackUpcaster::forClass(
            UpcastFixture::class,
            static fn (array $data): array => $data,
        );
        $beforeTransformUpcaster = CallbackUpcaster::forClass(
            UpcastFixture::class,
            static fn (array $data): array => $data,
        );

        $builder = new StackHydratorBuilder();
        $builder->useExtension(new UpcastExtension(
            beforeEncoding: [$beforeEncodingUpcaster],
            beforeTransform: [$beforeTransformUpcaster],
        ));
        $builder->useExtension(new CryptographyExtension($this->createMock(Cryptographer::class)));

        $middlewares = $builder->middlewares();

        self::assertCount(3, $middlewares);
        self::assertInstanceOf(UpcastMiddleware::class, $middlewares[0]);
        self::assertInstanceOf(CryptographyMiddleware::class, $middlewares[1]);
        self::assertInstanceOf(UpcastMiddleware::class, $middlewares[2]);
    }

    public function testConfigureAroundLegacyCryptography(): void
    {
        $beforeEncodingUpcaster = CallbackUpcaster::forClass(
            UpcastFixture::class,
            static fn (array $data): array => $data,
        );
        $beforeTransformUpcaster = CallbackUpcaster::forClass(
            UpcastFixture::class,
            static fn (array $data): array => $data,
        );

        $builder = new StackHydratorBuilder();
        $builder->useExtension(new UpcastExtension(
            beforeEncoding: [$beforeEncodingUpcaster],
            beforeTransform: [$beforeTransformUpcaster],
        ));
        $builder->useExtension(new CryptographyExtension(
            $this->createMock(Cryptographer::class),
            $this->createMock(PayloadCryptographer::class),
        ));

        $middlewares = $builder->middlewares();

        self::assertCount(4, $middlewares);
        self::assertInstanceOf(UpcastMiddleware::class, $middlewares[0]);
        self::assertInstanceOf(LegacyCryptographyDecryptMiddleware::class, $middlewares[1]);
        self::assertInstanceOf(CryptographyMiddleware::class, $middlewares[2]);
        self::assertInstanceOf(UpcastMiddleware::class, $middlewares[3]);
    }
}
