<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class ProfileCreatedWithInlineNormalizer
{
    public function __construct(
        public ProfileId $profileId,
        public ValueObject $valueObject,
    ) {
    }
}
