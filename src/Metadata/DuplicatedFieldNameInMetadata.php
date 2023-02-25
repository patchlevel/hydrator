<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Metadata;

use RuntimeException;

use function sprintf;

final class DuplicatedFieldNameInMetadata extends RuntimeException implements MetadataException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param class-string $classA
     * @param class-string $classB
     */
    public static function byInheritance(string $fieldName, string $classA, string $classB): self
    {
        return new self(
            sprintf(
                'field name "%s" is duplicated due to inheritance between %s and %s',
                $fieldName,
                $classA,
                $classB
            )
        );
    }

    /**
     * @param class-string $class
     */
    public static function inClass(string $fieldName, string $class, string $propertyA, string $propertyB): self
    {
        return new self(
            sprintf(
                'field name "%s" is duplicated in class %s (properties: "%s" and "%s")',
                $fieldName,
                $class,
                $propertyA,
                $propertyB
            )
        );
    }
}
