<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Cryptography\CryptographySubscriber;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Event\PostExtract;
use Patchlevel\Hydrator\Event\PreHydrate;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use ReflectionClass;
use stdClass;

#[CoversClass(CryptographySubscriber::class)]
final class CryptographySubscriberTest extends TestCase
{
    use ProphecyTrait;

    public function testSubscriptions(): void
    {
        self::assertEquals([
            PreHydrate::class => 'preHydrate',
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

        $cryptographer = $this->prophesize(PayloadCryptographer::class);
        $cryptographer->decrypt(
            $metadata,
            ['foo' => 'bar'],
        )->willReturn(['foo' => 'baz'])->shouldBeCalledOnce();

        $subscriber = new CryptographySubscriber($cryptographer->reveal());
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

        $cryptographer = $this->prophesize(PayloadCryptographer::class);
        $cryptographer->encrypt(
            $metadata,
            ['foo' => 'bar'],
        )->willReturn(['foo' => 'baz'])->shouldBeCalledOnce();

        $subscriber = new CryptographySubscriber($cryptographer->reveal());
        $subscriber->postExtract($event);

        self::assertEquals(['foo' => 'baz'], $event->data);
    }
}
