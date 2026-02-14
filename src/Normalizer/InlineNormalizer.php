<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use Closure;
use TypeError;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class InlineNormalizer implements Normalizer
{
    /**
     * @param Closure(O): N $normalize
     * @param Closure(N): O $denormalize
     *
     * @template O of mixed
     * @template N of mixed
     */
    public function __construct(
        private readonly Closure $normalize,
        private readonly Closure $denormalize,
        private readonly bool $passNull = false,
    ) {
    }

    public function normalize(mixed $value): mixed
    {
        if (!$this->passNull && $value === null) {
            return null;
        }

        try {
            return ($this->normalize)($value);
        } catch (TypeError) {
            throw new InvalidType();
        }
    }

    public function denormalize(mixed $value): mixed
    {
        if (!$this->passNull && $value === null) {
            return null;
        }

        try {
            return ($this->denormalize)($value);
        } catch (TypeError) {
            throw new InvalidType();
        }
    }
}
