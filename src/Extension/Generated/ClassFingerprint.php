<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use ReflectionClass;

use function class_exists;
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
        $array = false;

        if ($normalizer instanceof ArrayNormalizer) {
            $array = true;
            $normalizer = $normalizer->innerNormalizer();
        }

        if (!$normalizer instanceof ObjectNormalizer) {
            return null;
        }

        try {
            $class = $normalizer->className();
        } catch (InvalidType) {
            return null;
        }

        if (!class_exists($class)) {
            return null;
        }

        return [
            'class' => $class,
            'array' => $array,
            // the generated code checks the exact class of nested objects, which is cheaper for final classes
            'final' => (new ReflectionClass($class))->isFinal(),
        ];
    }
}
