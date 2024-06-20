<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;

final class InferNormalizerDto
{
    /** @param array<string> $array */
    public function __construct(
        public Status $status,
        public ProfileCreated $profileCreated,
        public DateTimeImmutable $dateTimeImmutable,
        public DateTime $dateTime,
        public DateTimeZone $dateTimeZone,
        public array $array,
    ) {
    }
}
