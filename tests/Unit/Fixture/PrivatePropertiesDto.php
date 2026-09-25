<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class PrivatePropertiesDto
{
    public function __construct(
        #[IdNormalizer]
        private readonly ProfileId $profileId,
        private string $name,
        protected int $age = 42,
    ) {
    }

    public function profileId(): ProfileId
    {
        return $this->profileId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function age(): int
    {
        return $this->age;
    }
}
