<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use DateTimeImmutable;

final class DistributionCreated extends DomainEvent
{
    public function __construct(
        DateTimeImmutable $distributionDate,
    ) {
        parent::__construct($distributionDate);
    }
}
