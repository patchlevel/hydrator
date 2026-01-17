<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Attribute;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class SensitiveData
{
    /** @var (callable(string):mixed)|null */
    public readonly mixed $fallbackCallable;

    public function __construct(
        public readonly mixed $fallback = null,
        callable|null $fallbackCallable = null,
        public readonly string $subjectIdName = 'default',
    ) {
        $this->fallbackCallable = $fallbackCallable;

        if ($this->fallbackCallable !== null && $this->fallback !== null) {
            throw new InvalidArgumentException('You can only set one of fallback or fallbackCallable');
        }
    }
}
