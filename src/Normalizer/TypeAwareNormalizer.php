<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Symfony\Component\TypeInfo\Type;

interface TypeAwareNormalizer
{
    /**
     * Allows to handle the type of the property.
     */
    public function handleType(Type|null $type): void;
}
