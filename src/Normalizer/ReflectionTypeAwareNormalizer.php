<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use ReflectionType;

/** @deprecated use TypeAwareNormalizer instead */
interface ReflectionTypeAwareNormalizer
{
    /**
     * Allows to handle the reflection type of the property.
     * Null means no reflection type exists.
     */
    public function handleReflectionType(ReflectionType|null $reflectionType): void;
}
