<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Guesser;

use Patchlevel\Hydrator\Normalizer\Normalizer;
use Symfony\Component\TypeInfo\Type\ObjectType;

interface Guesser
{
    public function guess(ObjectType $type): Normalizer|null;
}
