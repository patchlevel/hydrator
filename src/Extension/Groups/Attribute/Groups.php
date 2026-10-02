<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Groups\Attribute;

use Attribute;

use function array_values;
use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Groups
{
    /** @var list<string> */
    public readonly array $groups;

    /** @param string|array<string> $groups */
    public function __construct(string|array $groups)
    {
        $this->groups = is_string($groups) ? [$groups] : array_values($groups);
    }
}
