<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Mapper\Mapper;

/**
 * A nested class whose chain is not a generated mapper, for example because middlewares run for it. The generated code
 * then calls the whole chain, so the nested objects take the same way as through the hydrator.
 *
 * @internal
 */
final class ChainNestedMapper implements NestedMapper
{
    /** @param Mapper<object> $mapper */
    public function __construct(
        private readonly Mapper $mapper,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function hydrateNested(array $data, array $context): object
    {
        return $this->mapper->hydrate($data, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extractNested(object $object, array $context): array
    {
        return $this->mapper->extract($object, $context);
    }
}
