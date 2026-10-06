<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;

use function file_put_contents;
use function hash;
use function is_dir;
use function mkdir;
use function preg_replace;
use function rename;
use function serialize;
use function sprintf;
use function substr;
use function uniqid;
use function unlink;

/**
 * Where the generated transformer of a class is stored. The name contains the fingerprints of the class and its nested
 * classes, so a changed class simply has no generated code until it is generated again.
 *
 * @internal
 */
final class TransformerFiles
{
    public const NAMESPACE = 'Patchlevel\\Hydrator\\Generated';

    public function __construct(
        private readonly string $cachePath,
    ) {
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public function className(ClassMetadata $metadata, MetadataFactory $metadataFactory): string
    {
        $hash = hash('sha256', serialize([
            TransformerGenerator::VERSION,
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

    /** @throws GeneratedTransformerNotWritable */
    public function write(string $className, string $code): void
    {
        $file = $this->path($className);

        if (!is_dir($this->cachePath) && !@mkdir($this->cachePath, 0777, true) && !is_dir($this->cachePath)) {
            throw new GeneratedTransformerNotWritable($file);
        }

        $tmp = $file . '.' . uniqid('', true) . '.tmp';

        if (@file_put_contents($tmp, $code) === false || !@rename($tmp, $file)) {
            @unlink($tmp);

            throw new GeneratedTransformerNotWritable($file);
        }
    }
}
