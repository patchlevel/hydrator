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
 * Generates the transformers ahead of time, for example in a cache warmer or a deployment step. The code is generated
 * from the metadata, use the metadata factory of the builder so it matches the metadata of the hydrator:
 *
 *     (new GeneratedTransformerWarmer($builder->metadataFactory(), $cachePath))->warmup([ProfileCreated::class]);
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
     * Generates a transformer for every class and every nested class whose objects may be mapped in place.
     *
     * @param list<class-string> $classes
     *
     * @return list<class-string> all classes a transformer was generated for
     *
     * @throws ClassNotGeneratable if a class of the list can not be generated.
     * @throws GeneratedTransformerNotWritable
     */
    public function warmup(array $classes): array
    {
        $generator = new TransformerGenerator();
        $queue = [];

        foreach ($classes as $class) {
            $queue[] = [ltrim($class, '\\'), true];
        }

        $done = [];
        $generated = [];

        while ($queue !== []) {
            [$class, $requested] = array_shift($queue);

            if (isset($done[$class])) {
                continue;
            }

            $done[$class] = true;

            try {
                $metadata = $this->metadataFactory->metadata($class);
                $className = $this->files->className($metadata);
                $code = $generator->generate($metadata, $this->files->fqcn($className));
            } catch (ClassNotFound) {
                if ($requested) {
                    throw new ClassNotGeneratable($class, 'class not found');
                }

                continue;
            } catch (ClassNotGeneratable $e) {
                // nested classes without a transformer are hydrated by the hydrator
                if ($requested) {
                    throw $e;
                }

                continue;
            }

            $this->write($this->files->path($className), $code->code);
            $generated[] = $metadata->className;

            foreach ($code->nested as $nested) {
                $queue[] = [$nested, false];
            }
        }

        return $generated;
    }

    /** @throws GeneratedTransformerNotWritable */
    private function write(string $file, string $code): void
    {
        $directory = $this->files->cachePath;

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
