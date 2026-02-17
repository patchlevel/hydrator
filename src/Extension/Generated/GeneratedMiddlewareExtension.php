<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;

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
        $className = $this->className ?? $this->defaultClassName();
        $fqcn = self::NAMESPACE . '\\' . $className;

        if (!class_exists($fqcn, false)) {
            $this->load($fqcn, $className, $builder->getMetadataFactory());
        }

        $middleware = new $fqcn();
        assert($middleware instanceof Middleware);

        $builder->addMiddleware($middleware, Extension::PRIORITY_TRANSFORM + 1);
        $builder->setHydratorFactory(
            static fn (MetadataFactory $metadataFactory, array $middlewares, bool $defaultLazy): StackHydrator => new GeneratedHydrator(
                $metadataFactory,
                $middlewares,
                $defaultLazy,
            ),
        );
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
