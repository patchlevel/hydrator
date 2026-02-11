<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Guesser;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Patchlevel\Hydrator\Normalizer\DateIntervalNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeZoneNormalizer;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\ObjectType;

final readonly class BuiltInGuesser implements Guesser
{
    public function __construct(
        private bool $fallbackObjectNormalizer = true,
    ) {
    }

    public function guess(ObjectType $type): Normalizer|null
    {
        if ($type instanceof BackedEnumType) {
            return new EnumNormalizer($type->getClassName());
        }

        return match ($type->getClassName()) {
            DateInterval::class => new DateIntervalNormalizer(),
            DateTimeImmutable::class => new DateTimeImmutableNormalizer(),
            DateTime::class => new DateTimeNormalizer(),
            DateTimeZone::class => new DateTimeZoneNormalizer(),
            default => $this->fallbackObjectNormalizer ? new ObjectNormalizer($type->getClassName()) : null,
        };
    }
}
