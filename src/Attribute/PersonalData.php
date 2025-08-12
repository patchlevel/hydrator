<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Attribute;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class PersonalData
{
    /** @var (callable(string, mixed):mixed)|null */
    public readonly mixed $fallbackCallable;

    public function __construct(
        public readonly mixed $fallback = null,
        callable|null $fallbackCallable = null,
        public readonly string $identifier = 'default',
    ) {
        $this->fallbackCallable = $fallbackCallable;

        if ($this->fallbackCallable !== null && $this->fallback !== null) {
            throw new InvalidArgumentException('You can only set one of fallback or fallbackCallable');
        }
    }
}
