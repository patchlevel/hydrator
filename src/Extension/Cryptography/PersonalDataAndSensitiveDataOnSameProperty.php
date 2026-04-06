<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\Metadata\MetadataException;
use RuntimeException;

use function sprintf;

/** @experimental */
final class PersonalDataAndSensitiveDataOnSameProperty extends RuntimeException implements MetadataException
{
    /** @param class-string $class */
    public function __construct(string $class, string $property)
    {
        parent::__construct(
            sprintf(
                'Personal data and sensitive data cannot be used on the same property %s::%s',
                $class,
                $property,
            ),
        );
    }
}
