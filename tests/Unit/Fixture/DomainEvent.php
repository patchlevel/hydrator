<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use DateTimeImmutable;

abstract class DomainEvent
{
    public function __construct(
        protected readonly DateTimeImmutable $recordedDate,
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->recordedDate;
    }
}
