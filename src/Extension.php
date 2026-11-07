<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

interface Extension
{
    /** Reshape the raw stored payload before its values are decoded. */
    public const PRIORITY_BEFORE_ENCODING = 64;

    /** Encode or decode individual field values, the shape stays the same. */
    public const PRIORITY_ENCODING = 32;

    /** Last structural step before the array becomes an object. */
    public const PRIORITY_BEFORE_TRANSFORM = 0;

    /** Build the object from the array and deconstruct it again. */
    public const PRIORITY_TRANSFORM = -64;

    public function configure(StackHydratorBuilder $builder): void;
}
