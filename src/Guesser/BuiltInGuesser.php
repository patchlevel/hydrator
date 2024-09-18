<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Guesser;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeZoneNormalizer;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;

use function is_a;

final class BuiltInGuesser implements Guesser
{
    /** @param class-string $className */
    public function guess(string $className): Normalizer|null
    {
        $normalizer = match ($className) {
            DateTimeImmutable::class => new DateTimeImmutableNormalizer(),
            DateTime::class => new DateTimeNormalizer(),
            DateTimeZone::class => new DateTimeZoneNormalizer(),
            default => null,
        };

        if ($normalizer) {
            return $normalizer;
        }

        if (is_a($className, BackedEnum::class, true)) {
            return new EnumNormalizer($className);
        }

        return null;
    }
}
