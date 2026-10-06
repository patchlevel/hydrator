<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;

use function hash;
use function preg_replace;
use function serialize;
use function sprintf;
use function substr;

/**
 * Where the generated transformer of a class is stored. The name contains the fingerprints of the class and its nested
 * classes, so a changed class simply has no generated code until the warmup runs again.
 *
 * Used at runtime, so it must not depend on the code generation.
 *
 * @internal
 */
final class TransformerFiles
{
    public const NAMESPACE = 'Patchlevel\\Hydrator\\Generated';

    /** Part of the file name, bump it whenever the generated code changes. */
    public const VERSION = 1;

    public function __construct(
        private readonly string $cachePath,
    ) {
    }

    /**
     * Constructor visibility does not matter, the generated code runs in the scope of the class.
     *
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public static function generatable(ClassMetadata $metadata): bool
    {
        $reflection = $metadata->reflection;

        return !$reflection->isInternal()
            && !$reflection->isAbstract()
            && !$reflection->isInterface()
            && !$reflection->isEnum()
            && !$reflection->isAnonymous();
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public function className(ClassMetadata $metadata, MetadataFactory $metadataFactory): string
    {
        $hash = hash('sha256', serialize([
            self::VERSION,
            $metadata->className,
            ClassFingerprint::ofGraph($metadata, $metadataFactory),
        ]));

        return sprintf(
            '%sTransformer_%s',
            preg_replace('/[^A-Za-z0-9_]/', '', $metadata->reflection->getShortName()),
            substr($hash, 0, 16),
        );
    }

    /** @return class-string */
    public function fqcn(string $className): string
    {
        /** @var class-string $fqcn */
        $fqcn = self::NAMESPACE . '\\' . $className;

        return $fqcn;
    }

    public function path(string $className): string
    {
        return sprintf('%s/%s.php', $this->cachePath, $className);
    }

    public function cachePath(): string
    {
        return $this->cachePath;
    }
}
