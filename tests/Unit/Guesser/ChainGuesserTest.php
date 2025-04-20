<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Guesser;

use DateTimeImmutable;
use Patchlevel\Hydrator\Guesser\ChainGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

final class ChainGuesserTest extends TestCase
{
    public function testGuessReturnsFirstNonNullResult(): void
    {
        $guesser1 = $this->createMock(Guesser::class);
        $guesser1->expects($this->once())
            ->method('guess')
            ->willReturn(null);

        $expectedNormalizer = new DateTimeImmutableNormalizer();

        $guesser2 = $this->createMock(Guesser::class);
        $guesser2->expects($this->once())
            ->method('guess')
            ->willReturn($expectedNormalizer);

        $guesser3 = $this->createMock(Guesser::class);
        $guesser3->expects($this->never())
            ->method('guess');

        $chainGuesser = new ChainGuesser([$guesser1, $guesser2, $guesser3]);

        $result = $chainGuesser->guess(Type::object(DateTimeImmutable::class));

        $this->assertSame($expectedNormalizer, $result);
    }

    public function testGuessReturnsNullIfAllGuessersReturnNull(): void
    {
        $guesser1 = $this->createMock(Guesser::class);
        $guesser1->expects($this->once())
            ->method('guess')
            ->willReturn(null);

        $guesser2 = $this->createMock(Guesser::class);
        $guesser2->expects($this->once())
            ->method('guess')
            ->willReturn(null);

        $chainGuesser = new ChainGuesser([$guesser1, $guesser2]);

        $result = $chainGuesser->guess(Type::object(DateTimeImmutable::class));

        $this->assertNull($result);
    }
}
