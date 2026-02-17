<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;

use function array_splice;
use function assert;
use function class_exists;
use function file_exists;
use function file_put_contents;
use function hash;
use function is_dir;
use function mkdir;
use function rename;
use function serialize;
use function sprintf;
use function substr;
use function uniqid;
use function unlink;

final class GeneratedMiddlewareExtension implements Extension
{
    public const NAMESPACE = 'Patchlevel\\Hydrator\\Generated';

    /**
     * @param string             $cachePath Directory where the generated middleware is stored.
     * @param list<class-string> $classes   Classes for which code is generated, nested classes are discovered automatically.
     * @param bool               $debug     Regenerate the middleware on every build.
     * @param string|null        $className Name of the generated class, derived from the configuration by default.
     */
    public function __construct(
        private readonly string $cachePath,
        private readonly array $classes,
        private readonly bool $debug = false,
        private readonly string|null $className = null,
    ) {
    }

    public function configure(StackHydratorBuilder $builder): void
    {
        // the code is generated when the hydrator is built, not here: extensions registered later may still add
        // guessers and enrichers, and the generated code has to match the metadata the hydrator ends up with
        $builder->setHydratorFactory(
            fn (MetadataFactory $metadataFactory, array $middlewares, bool $defaultLazy): StackHydrator => new GeneratedHydrator(
                $metadataFactory,
                $this->addMiddleware($middlewares, $this->middleware($metadataFactory)),
                $defaultLazy,
            ),
        );
    }

    private function middleware(MetadataFactory $metadataFactory): Middleware
    {
        $className = $this->className ?? $this->defaultClassName();
        $fqcn = self::NAMESPACE . '\\' . $className;

        if (!class_exists($fqcn, false)) {
            $this->load($fqcn, $className, $metadataFactory);
        }

        $middleware = new $fqcn();
        assert($middleware instanceof Middleware);

        return $middleware;
    }

    /**
     * The generated middleware runs right before the TransformMiddleware, like a middleware registered with
     * Extension::PRIORITY_TRANSFORM + 1 would.
     *
     * @param list<Middleware> $middlewares
     *
     * @return list<Middleware>
     */
    private function addMiddleware(array $middlewares, Middleware $generated): array
    {
        foreach ($middlewares as $index => $middleware) {
            if ($middleware instanceof TransformMiddleware) {
                array_splice($middlewares, $index, 0, [$generated]);

                return $middlewares;
            }
        }

        $middlewares[] = $generated;

        return $middlewares;
    }

    public function defaultClassName(): string
    {
        return 'TransformMiddleware_' . substr(
            hash('sha256', serialize([MiddlewareGenerator::VERSION, $this->classes])),
            0,
            16,
        );
    }

    private function load(string $fqcn, string $className, MetadataFactory $metadataFactory): void
    {
        $file = sprintf('%s/%s.php', $this->cachePath, $className);

        if ($this->debug || !file_exists($file)) {
            $generator = new MiddlewareGenerator($metadataFactory);
            $this->write($file, $generator->dump($this->classes, $fqcn));
        }

        require $file;
    }

    private function write(string $file, string $code): void
    {
        if (!is_dir($this->cachePath) && !@mkdir($this->cachePath, 0777, true) && !is_dir($this->cachePath)) {
            throw new GeneratedMiddlewareNotWritable($file);
        }

        $tmp = $file . '.' . uniqid('', true) . '.tmp';

        if (@file_put_contents($tmp, $code) === false || !@rename($tmp, $file)) {
            @unlink($tmp);

            throw new GeneratedMiddlewareNotWritable($file);
        }
    }
}
