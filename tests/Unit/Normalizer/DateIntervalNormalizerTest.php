<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use DateInterval;
use Patchlevel\Hydrator\Normalizer\DateIntervalNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use PHPUnit\Framework\TestCase;

final class DateIntervalNormalizerTest extends TestCase
{
    public function testNormalizeWithNull(): void
    {
        $normalizer = new DateIntervalNormalizer();
        self::assertNull($normalizer->normalize(null, []));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new DateIntervalNormalizer();
        self::assertNull($normalizer->denormalize(null, []));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);

        $normalizer = new DateIntervalNormalizer();
        $normalizer->normalize(123, []);
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionCode(0);

        $normalizer = new DateIntervalNormalizer();
        $normalizer->denormalize(123, []);
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new DateIntervalNormalizer();
        self::assertSame('P02Y02M25DT06H07M08S', $normalizer->normalize(new DateInterval('P2Y2M3W4DT6H7M8S'), []));
    }

    public function testNormalizeWithChangeFormat(): void
    {
        $normalizer = new DateIntervalNormalizer(format: 'P%YY%MM');
        self::assertSame('P02Y02M', $normalizer->normalize(new DateInterval('P2Y2M3W4DT6H7M8S'), []));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new DateIntervalNormalizer();
        $denormalized = $normalizer->denormalize('P00Y00M35DT00H00M00S', []);
        self::assertNotNull($denormalized);

        $this->assertEqualInterval(
            new DateInterval('P5W'),
            $denormalized,
        );
    }

    public function testDenormalizeWithChangeFormat(): void
    {
        $normalizer = new DateIntervalNormalizer(format: 'P%YY');
        $denormalized = $normalizer->denormalize('P5Y', []);
        self::assertNotNull($denormalized);

        $this->assertEqualInterval(
            new DateInterval('P5Y'),
            $denormalized,
        );
    }

    public function testDateIntervalErrorsAreCaughtAndReThrown(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Invalid serialized date interval string');
        $this->expectExceptionCode(0);

        (new DateIntervalNormalizer())->denormalize('Kermit', []);
    }

    private function assertEqualInterval(DateInterval $a, DateInterval $b): void
    {
        self::assertSame($a->y, $b->y);
        self::assertSame($a->m, $b->m);
        self::assertSame($a->d, $b->d);
        self::assertSame($a->h, $b->h);
        self::assertSame($a->i, $b->i);
        self::assertSame($a->s, $b->s);
        self::assertSame($a->invert, $b->invert);
        self::assertSame($a->f, $b->f);
        self::assertSame($a->days, $b->days);
    }
}
