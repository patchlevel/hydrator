<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class NormalizedName
{
    /** @param list<string> $aliases former names of the field, they are read while hydrating if the field is missing */
    public function __construct(
        private readonly string $name,
        private readonly array $aliases = [],
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function aliases(): array
    {
        return $this->aliases;
    }
}
