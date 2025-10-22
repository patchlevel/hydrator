<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Cryptography\CryptographySubscriber;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Event\PostExtract;
use Patchlevel\Hydrator\Event\PreExtract;
use Patchlevel\Hydrator\Event\PreHydrate;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

#[CoversClass(CryptographySubscriber::class)]
final class CryptographySubscriberTest extends TestCase
{
    public function testSubscriptions(): void
    {
        self::assertEquals([
            PreHydrate::class => 'preHydrate',
            PreExtract::class => 'preExtract',
            PostExtract::class => 'postExtract',
        ], CryptographySubscriber::getSubscribedEvents());
    }

    public function testPreHydrate(): void
    {
        $metadata = new ClassMetadata(
            new ReflectionClass(stdClass::class),
        );

        $event = new PreHydrate(
            ['foo' => 'bar'],
            $metadata,
        );

        $cryptographer = $this->createMock(PayloadCryptographer::class);
        $cryptographer->expects($this->once())->method('decrypt')->with($metadata, ['foo' => 'bar'])->willReturn(['foo' => 'baz']);

        $subscriber = new CryptographySubscriber($cryptographer);
        $subscriber->preHydrate($event);

        self::assertEquals(['foo' => 'baz'], $event->data);
    }

    public function testPostExtract(): void
    {
        $metadata = new ClassMetadata(
            new ReflectionClass(stdClass::class),
        );

        $event = new PostExtract(
            ['foo' => 'bar'],
            $metadata,
        );

        $cryptographer = $this->createMock(PayloadCryptographer::class);
        $cryptographer->expects($this->once())->method('encrypt')->with($metadata, ['foo' => 'bar'])->willReturn(['foo' => 'baz']);

        $subscriber = new CryptographySubscriber($cryptographer);
        $subscriber->postExtract($event);

        self::assertEquals(['foo' => 'baz'], $event->data);
    }
}
