<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use RuntimeException;

use function sprintf;

final class NotPersonalData extends RuntimeException
{
    /** @param class-string $class */
    public function __construct(string $class, string $fieldName)
    {
        parent::__construct(sprintf('Trying to get subject id for %s::%s which is not marked as personal data.', $class, $fieldName));
    }
}
