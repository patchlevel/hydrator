<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use function hash;

/**
 * PSR-6 and PSR-16 reserve the characters {}()/\@: and only guarantee keys up to 64 characters,
 * so ids and subject ids are hashed instead of being used as they are.
 *
 * @internal
 */
final class CacheKey
{
    public static function forId(string $id): string
    {
        return 'cipher_key_' . hash('xxh128', $id);
    }

    public static function forSubjectId(string $subjectId): string
    {
        return 'cipher_subject_' . hash('xxh128', $subjectId);
    }

    public static function forSubjectKeyIds(string $subjectId): string
    {
        return 'cipher_subject_keys_' . hash('xxh128', $subjectId);
    }
}
