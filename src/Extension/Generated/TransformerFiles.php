<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

use function hash;
use function preg_replace;
use function serialize;
use function sprintf;
use function substr;

/**
 * Where the generated transformer of a class is stored. Shared by the runtime and the warmup, so both derive the same
 * name. The name contains the {@see ClassFingerprint}, so a changed class has no generated transformer until it is
 * warmed up again.
 *
 * @internal
 */
final class TransformerFiles
{
    public const NAMESPACE = 'Patchlevel\\Hydrator\\Generated';

    /** Part of the name, bump it whenever the generated code changes. */
    public const VERSION = 10;

    public function __construct(
        public readonly string $cachePath,
    ) {
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public function className(ClassMetadata $metadata): string
    {
        $hash = hash('sha256', serialize([self::VERSION, $metadata->className, ClassFingerprint::of($metadata)]));

        return sprintf(
            '%sTransformer_%s',
            preg_replace('/[^A-Za-z0-9_]/', '', $metadata->reflection->getShortName()),
            substr($hash, 0, 16),
        );
    }

    public function fqcn(string $className): string
    {
        return self::NAMESPACE . '\\' . $className;
    }

    public function path(string $className): string
    {
        return sprintf('%s/%s.php', $this->cachePath, $className);
    }
}
