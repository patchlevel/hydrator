<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use function hash;

/**
 * PSR-6 and PSR-16 reserve the characters {}()/\@: and only guarantee keys up to 64 characters,
 * so class names are hashed instead of being used as they are.
 *
 * @internal
 */
final class CacheKey
{
    /** @param class-string $class */
    public static function forClass(string $class): string
    {
        return 'hydrator_metadata_' . hash('xxh128', $class);
    }
}
