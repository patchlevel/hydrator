<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Guesser;

use Patchlevel\Hydrator\Normalizer\Normalizer;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function array_key_exists;

final readonly class MappedGuesser implements Guesser
{
    /** @param array<class-string, class-string<Normalizer>> $map */
    public function __construct(private array $map)
    {
    }

    /**
     * @param ObjectType<T> $type
     *
     * @template T
     */
    public function guess(ObjectType $type): Normalizer|null
    {
        $className = $type->getClassName();
        if (! array_key_exists($className, $this->map)) {
            return null;
        }

        $normalizerType = $this->map[$className];

        return new $normalizerType();
    }
}
