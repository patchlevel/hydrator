<?php

declare(strict_types=1);

namespace Unit\Middleware;

use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransformMiddleware::class)]
class TransformerMiddlewareTest extends TestCase
{
    public function testHydrate(): void
    {
        $middleware = new TransformMiddleware();

        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $event = $middleware->hydrate(
            $this->classMetadata(ProfileCreated::class),
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            [],
            new Stack([]),
        );

        self::assertEquals($expected, $event);
    }

    public function testExtract(): void
    {
        $middleware = new TransformMiddleware();

        $expected = ['profileId' => '1', 'email' => 'info@patchlevel.de'];

        $data = $middleware->extract(
            $this->classMetadata(ProfileCreated::class),
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
            [],
            new Stack([]),
        );

        self::assertEquals($expected, $data);
    }

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    private function classMetadata(string $class): ClassMetadata
    {
        return (new AttributeMetadataFactory())
            ->metadata($class);
    }
}
