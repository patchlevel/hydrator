<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Guesser;

use Patchlevel\Hydrator\Normalizer\Normalizer;
use Symfony\Component\TypeInfo\Type\ObjectType;

final class ChainGuesser implements Guesser
{
    /** @param iterable<Guesser> $guessers */
    public function __construct(
        private readonly iterable $guessers,
    ) {
    }

    public function guess(ObjectType $type): Normalizer|null
    {
        foreach ($this->guessers as $guesser) {
            $normalizer = $guesser->guess($type);

            if ($normalizer !== null) {
                return $normalizer;
            }
        }

        return null;
    }
}
{

}
