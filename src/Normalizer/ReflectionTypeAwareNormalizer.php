<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use ReflectionType;

interface ReflectionTypeAwareNormalizer
{
    public function setReflectionType(ReflectionType $reflectionType): void;
}
