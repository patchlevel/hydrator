<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use DateTimeImmutable;

final class InferNormalizerWithNullableItemsDto
{
    /**
     * @param list<?Skill>                      $skills
     * @param array<string, ?Status>            $statuses
     * @param list<DateTimeImmutable|null>      $dates
     * @param array<string, list<?Status>>|null $nested
     */
    public function __construct(
        public array $skills = [],
        public array $statuses = [],
        public array $dates = [],
        public array|null $nested = null,
    ) {
    }
}
