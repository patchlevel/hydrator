<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

use function array_pop;
use function hash;
use function ksort;
use function serialize;
use function substr;

/**
 * Fingerprint of everything the generated code of a class depends on: the properties with their field names,
 * normalizers, visibility, declaring class and inlined defaults. The fingerprints of a class and its nested classes are
 * part of the name of the generated transformer, and nested classes are only inlined if their fingerprint still matches.
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

    /**
     * Fingerprint of the class together with every class reachable through object normalizers, since the generated
     * code of a class contains the inlined code of its nested classes.
     *
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public static function ofGraph(ClassMetadata $metadata, MetadataFactory $metadataFactory): string
    {
        /** @var array<class-string, string> $fingerprints */
        $fingerprints = [];
        $queue = [$metadata];

        while ($queue !== []) {
            $current = array_pop($queue);

            if (isset($fingerprints[$current->className])) {
                continue;
            }

            $fingerprints[$current->className] = self::of($current);

            foreach ($current->properties as $property) {
                $nested = self::nestedClass($property->normalizer);

                if ($nested === null || isset($fingerprints[$nested])) {
                    continue;
                }

                try {
                    $queue[] = $metadataFactory->metadata($nested);
                } catch (ClassNotFound) {
                    continue;
                }
            }
        }

        ksort($fingerprints);

        return substr(hash('sha256', serialize($fingerprints)), 0, 16);
    }

    /** @return class-string|null */
    private static function nestedClass(Normalizer|null $normalizer): string|null
    {
        if ($normalizer instanceof ArrayNormalizer) {
            $normalizer = $normalizer->innerNormalizer();
        }

        if (!$normalizer instanceof ObjectNormalizer) {
            return null;
        }

        try {
            return $normalizer->className();
        } catch (InvalidType) {
            return null;
        }
    }
}
