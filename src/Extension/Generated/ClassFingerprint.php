<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

use function hash;
use function serialize;
use function substr;

/**
 * Fingerprint of everything the generated code of a class depends on: the properties with their field names,
 * normalizers, visibility, declaring class and inlined defaults. The generated middleware compares it with the
 * current metadata on initialization and refuses to run with outdated code.
 *
 * @internal
 */
final class ClassFingerprint
{
    /** @param ClassMetadata<object> $metadata */
    public static function of(ClassMetadata $metadata): string
    {
        $defaults = $metadata->promotedConstructorDefaults();
        $shape = [$metadata->reflection->isFinal()];

        foreach ($metadata->properties as $property) {
            $reflection = $property->reflection;
            $default = null;

            if ($reflection->isPromoted() && isset($defaults[$property->propertyName])) {
                // defaults which can not be inlined are resolved at runtime, their value does not matter here
                $default = Literal::export($defaults[$property->propertyName]->getDefaultValue()) ?? 'runtime';
            }

            $declaringClass = $reflection->getDeclaringClass()->getName();

            $shape[] = [
                $property->propertyName,
                $property->fieldName,
                $property->normalizer !== null,
                $reflection->getModifiers(),
                // inherited properties may need the scope of their declaring class
                $declaringClass === $metadata->className ? null : $declaringClass,
                $default,
            ];
        }

        return substr(hash('sha256', serialize($shape)), 0, 16);
    }
}
