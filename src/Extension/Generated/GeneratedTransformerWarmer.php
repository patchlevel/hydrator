<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataFactory;

use function array_shift;
use function file_put_contents;
use function is_dir;
use function ltrim;
use function mkdir;
use function rename;
use function uniqid;
use function unlink;

/**
 * Generates the transformers ahead of time, for example when the application is deployed. Pass the hydrator as
 * metadata factory, so the code matches the metadata it works with:
 *
 *     (new GeneratedTransformerWarmer($hydrator, $cachePath))->warmup([ProfileCreated::class]);
 */
final class GeneratedTransformerWarmer
{
    private readonly TransformerFiles $files;

    public function __construct(
        private readonly MetadataFactory $metadataFactory,
        string $cachePath,
    ) {
        $this->files = new TransformerFiles($cachePath);
    }

    /**
     * Generates a transformer for every class and every nested class it references.
     *
     * @param list<class-string> $classes
     *
     * @return list<class-string> all classes a transformer was generated for
     *
     * @throws ClassNotGeneratable
     * @throws GeneratedTransformerNotWritable
     */
    public function warmup(array $classes): array
    {
        $generator = new TransformerGenerator($this->metadataFactory);
        $queue = $classes;
        $done = [];
        $generated = [];

        while ($queue !== []) {
            /** @var class-string $class */
            $class = ltrim(array_shift($queue), '\\');

            if (isset($done[$class])) {
                continue;
            }

            $done[$class] = true;

            try {
                $metadata = $this->metadataFactory->metadata($class);
            } catch (ClassNotFound) {
                throw new ClassNotGeneratable($class, 'class not found');
            }

            $className = $this->files->className($metadata, $this->metadataFactory);
            $code = $generator->generate($class, $this->files->fqcn($className));

            if ($code === null) {
                continue;
            }

            $this->write($this->files->path($className), $code->code);
            $generated[] = $class;

            // nested classes are also hydrated on their own if they can not be inlined
            foreach ($code->classes as $nested) {
                $queue[] = $nested;
            }
        }

        return $generated;
    }

    /** @throws GeneratedTransformerNotWritable */
    private function write(string $file, string $code): void
    {
        $directory = $this->files->cachePath();

        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new GeneratedTransformerNotWritable($file);
        }

        $tmp = $file . '.' . uniqid('', true) . '.tmp';

        if (@file_put_contents($tmp, $code) === false || !@rename($tmp, $file)) {
            @unlink($tmp);

            throw new GeneratedTransformerNotWritable($file);
        }
    }
}
