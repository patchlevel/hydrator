<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Guesser\BuiltInGuesser;
use Patchlevel\Hydrator\HydratorBuilder;

final class GeneratedCoreExtension implements Extension
{
    public function __construct(
        private readonly string $cachePath,
        private readonly array $classes,
    )
    {
    }

    //@todo most probably this is not the best idea to add this as an extension, as we have here some bidirectional dependencies
    public function configure(HydratorBuilder $builder): void
    {
        $builder->addGuesser(new BuiltInGuesser(), -64); // @todo this should be somehow considered in generator
        $metadataFactory = $builder->getMetadataFactory();

        $generator = new MiddlewareGenerator($metadataFactory);
        $middlewareClassName = 'GeneratedTransformMiddleware';
        $fullMiddlewareClassName = 'Patchlevel\\Hydrator\\Generated\\' . $middlewareClassName;

        $middlewareCode = $generator->dump($this->classes, $fullMiddlewareClassName);

        //if (class_exists($fullMiddlewareClassName)) {
        //    throw new \RuntimeException(sprintf('Middleware class %s already exists', $fullMiddlewareClassName));
        //}

        $filename = sprintf('%s/%s.php', $this->cachePath, $middlewareClassName);

        //if (file_exists($filename)) {
        //    throw new \RuntimeException(sprintf('Middleware file %s already exists', $filename));
        //}
//
        //if (!is_dir(dirname($filename))) {
        //    mkdir(dirname($filename), 0777, true);
        //}

        file_put_contents($filename, $middlewareCode);
        require_once $filename; // should not be needed if autoload config is valid?

        $builder->addMiddleware(new $fullMiddlewareClassName($metadataFactory), -64);
    }
}
