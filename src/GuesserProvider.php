<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Guesser\Guesser;

interface GuesserProvider
{
    /** @return iterable<Guesser|array{0: Guesser, 1?: int}> */
    public function guesser(): iterable;
}
