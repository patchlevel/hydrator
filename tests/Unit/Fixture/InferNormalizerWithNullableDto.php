<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;

final class InferNormalizerWithNullableDto
{
    public function __construct(
        public Status|null $status,
        public DateTimeImmutable|null $dateTimeImmutable,
        public DateTime|null $dateTime = null,
        public DateTimeZone|null $dateTimeZone = null,
    ) {
    }
}
