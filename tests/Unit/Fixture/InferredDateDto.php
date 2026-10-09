<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use DateTimeImmutable;

/** The normalizer of the date is inferred by the guesser of the CoreExtension. */
final class InferredDateDto
{
    public function __construct(
        public DateTimeImmutable $createdAt,
    ) {
    }
}
