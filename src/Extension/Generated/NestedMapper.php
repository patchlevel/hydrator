<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

/**
 * The entry points a generated mapper calls for nested objects. The parent mapper already checked the call, so they
 * skip the checks of hydrate() and extract().
 *
 * @internal
 */
interface NestedMapper
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context the call comes from the hydrator of the mapper, without an object to populate
     */
    public function hydrateNested(array $data, array $context): object;

    /**
     * @param array<string, mixed> $context the call comes from the hydrator of the mapper
     *
     * @return array<string, mixed>
     */
    public function extractNested(object $object, array $context): array;
}
