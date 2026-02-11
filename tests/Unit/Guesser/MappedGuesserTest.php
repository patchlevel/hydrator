<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Guesser;

use Patchlevel\Hydrator\Guesser\MappedGuesser;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type\ObjectType;

final class MappedGuesserTest extends TestCase
{
    public function testTheNormalizerIsNullWhenTheTypeHasNotBeenMapped(): void
    {
        $guesser = new MappedGuesser([]);

        self::assertNull($guesser->guess(new ObjectType(Email::class)));
    }

    public function testTheNormalizerIsOfTheExpectedType(): void
    {
        $guesser = new MappedGuesser([
            Email::class => EmailNormalizer::class,
        ]);

        $normalizer = $guesser->guess(new ObjectType(Email::class));

        self::assertInstanceOf(EmailNormalizer::class, $normalizer);
    }
}
