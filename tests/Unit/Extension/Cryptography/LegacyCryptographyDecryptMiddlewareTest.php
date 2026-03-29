<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography;

use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\LegacyCryptographyDecryptMiddleware;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyCryptographyDecryptMiddleware::class)]
final class LegacyCryptographyDecryptMiddlewareTest extends TestCase
{
    public function testHydrateSetsLegacyContextIfDataWasDecrypted(): void
    {
        $data = ['profileId' => 'foo', 'email' => 'encrypted'];
        $processedData = ['profileId' => 'foo', 'email' => 'info@patchlevel.de'];
        $metadata = $this->metadata(ProfileCreated::class);

        $cryptographer = $this->createMock(PayloadCryptographer::class);
        $cryptographer
            ->expects($this->once())
            ->method('decrypt')
            ->with($metadata, $data)
            ->willReturn($processedData);

        $expected = new ProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );

        $otherMiddleware = $this->createMock(Middleware::class);
        $stack = new Stack([$otherMiddleware]);

        $otherMiddleware
            ->expects($this->once())
            ->method('hydrate')
            ->with(
                $metadata,
                $processedData,
                [LegacyCryptographyDecryptMiddleware::class => true],
                $stack,
            )
            ->willReturn($expected);

        $middleware = new LegacyCryptographyDecryptMiddleware($cryptographer);

        $result = $middleware->hydrate($metadata, $data, [], $stack);

        self::assertSame($expected, $result);
    }

    public function testHydrateDoesNotSetLegacyContextIfDataWasNotDecrypted(): void
    {
        $data = ['profileId' => 'foo', 'email' => 'info@patchlevel.de'];
        $metadata = $this->metadata(ProfileCreated::class);

        $cryptographer = $this->createMock(PayloadCryptographer::class);
        $cryptographer
            ->expects($this->once())
            ->method('decrypt')
            ->with($metadata, $data)
            ->willReturn($data);

        $expected = new ProfileCreated(
            ProfileId::fromString('foo'),
            Email::fromString('info@patchlevel.de'),
        );

        $otherMiddleware = $this->createMock(Middleware::class);
        $stack = new Stack([$otherMiddleware]);

        $otherMiddleware
            ->expects($this->once())
            ->method('hydrate')
            ->with($metadata, $data, [], $stack)
            ->willReturn($expected);

        $middleware = new LegacyCryptographyDecryptMiddleware($cryptographer);

        $result = $middleware->hydrate($metadata, $data, [], $stack);

        self::assertSame($expected, $result);
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        return (new AttributeMetadataFactory())->metadata($class);
    }
}
