<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Guesser;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Patchlevel\Hydrator\Guesser\BuiltInGuesser;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeZoneNormalizer;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use PHPUnit\Framework\TestCase;

final class BuiltInGuesserTest extends TestCase
{
    public function testNoMatch(): void
    {
        $guesser = new BuiltInGuesser();
        self::assertNull($guesser->guess(Email::class));
    }

    public function testEnum(): void
    {
        $guesser = new BuiltInGuesser();
        self::assertInstanceOf(
            EnumNormalizer::class,
            $guesser->guess(Status::class),
        );
    }

    public function testDateTimeImmutable(): void
    {
        $guesser = new BuiltInGuesser();
        self::assertInstanceOf(
            DateTimeImmutableNormalizer::class,
            $guesser->guess(DateTimeImmutable::class),
        );
    }

    public function testDateTime(): void
    {
        $guesser = new BuiltInGuesser();
        self::assertInstanceOf(
            DateTimeNormalizer::class,
            $guesser->guess(DateTime::class),
        );
    }

    public function testDateTimeZone(): void
    {
        $guesser = new BuiltInGuesser();
        self::assertInstanceOf(
            DateTimeZoneNormalizer::class,
            $guesser->guess(DateTimeZone::class),
        );
    }
}
