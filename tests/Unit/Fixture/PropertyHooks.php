<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class PropertyHooks
{
    public string $fullName {
        get {
            return $this->firstName.' '.$this->lastName;
        }
    }

    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName
    ) {
        //
    }
}
