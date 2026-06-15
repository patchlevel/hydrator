<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\Extension\Generated\MiddlewareGenerator;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\Middleware\AttributeTransformMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransformMiddleware::class)]
class GeneratedTransformerMiddlewareTest extends TestCase
{
    public function testHydrate(): void
    {
        $cachePath = __DIR__ . '/../../../../var/cache';
        @mkdir($cachePath, 0777, true);

        $metadataFactory = new AttributeMetadataFactory();
        $generator = new MiddlewareGenerator($metadataFactory);
        $middlewareClassName = 'GeneratedTransformMiddleware';
        $fullMiddlewareClassName = 'Patchlevel\\Hydrator\\Generated\\' . $middlewareClassName;
        $filename = sprintf('%s/%s.php', $cachePath, $middlewareClassName);

        $middlewareCode = $generator->dump([ProfileCreated::class], $fullMiddlewareClassName);
        file_put_contents($filename, $middlewareCode);

        require_once $filename;

        $middleware = new $fullMiddlewareClassName();

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
        $cachePath = __DIR__ . '/../../../var/cache';
        @mkdir($cachePath, 0777, true);

        $metadataFactory = new AttributeMetadataFactory();
        $generator = new MiddlewareGenerator($metadataFactory);
        $generatedClassName = 'UnifiedMiddleware';
        $code = $generator->generate([ProfileCreated::class], $generatedClassName);
        file_put_contents($cachePath . '/' . $generatedClassName . '.php', $code);

        $middleware = new AttributeTransformMiddleware(
            $cachePath,
            [ProfileCreated::class],
            $metadataFactory
        );

        $expected = ['profileId' => '1', 'email' => 'info@patchlevel.de'];

        $data = $middleware->extract(
            $this->classMetadata(ProfileCreated::class),
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
            [],
            new Stack([], new MetadataHydrator()),
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
