<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography\Store;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

/** @experimental */
final class CipherKeyNotExists extends RuntimeException implements HydratorException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forKeyId(string $id): self
    {
        return new self(sprintf('Cipher key with id "%s" does not exist.', $id));
    }

    public static function forSubjectId(string $subjectId): self
    {
        return new self(sprintf('Cipher key for subject id "%s" does not exist.', $subjectId));
    }
}
