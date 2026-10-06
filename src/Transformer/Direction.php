<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

enum Direction
{
    case Hydrate;
    case Extract;
}
