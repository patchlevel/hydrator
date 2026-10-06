<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\NestedObject;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use ReflectionClass;

use function hash;
use function serialize;
use function substr;

/**
 * Fingerprint of everything the generated code of a class depends on: the properties with their field names,
 * normalizers, visibility, declaring class and inlined defaults, and the nested classes. It is part of the name of the
 * generated transformer, so a changed class has no generated transformer until it is warmed up again.
 *
 * @internal
 */
final class ClassFingerprint
{
    /**
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
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
            $nested = null;

            if ($property->normalizer !== null) {
                $nested = self::nested($property->normalizer);
            }

            $shape[] = [
                $property->propertyName,
                $property->fieldName,
                $property->normalizer !== null,
                $reflection->getModifiers(),
                // inherited properties may need the scope of their declaring class
                $declaringClass === $metadata->className ? null : $declaringClass,
                $default,
                $nested,
            ];
        }

        return substr(hash('sha256', serialize($shape)), 0, 16);
    }

    /**
     * Nested objects are mapped in place if they are normalized with an object normalizer, directly or in an array.
     *
     * @return array{class: class-string, array: bool, final: bool}|null
     */
    public static function nested(Normalizer $normalizer): array|null
    {
        $nested = NestedObject::of($normalizer);

        if ($nested === null) {
            return null;
        }

        return [
            'class' => $nested->className,
            'array' => $nested->list,
            // the generated code checks the exact class of nested objects, which is cheaper for final classes
            'final' => (new ReflectionClass($nested->className))->isFinal(),
        ];
    }
}
