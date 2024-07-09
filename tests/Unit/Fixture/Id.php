<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

#[IdNormalizer]
interface Id
{
    public static function fromString(string $id): self;

    public function toString(): string;
}
