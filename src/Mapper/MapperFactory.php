<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Mapper;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;

/**
 * Provides the mapper of a class, for example generated code. Factories are registered with
 * {@see \Patchlevel\Hydrator\StackHydratorBuilder::addMapperFactory()}, the first one which returns a mapper wins.
 * Classes without a mapper of a factory are mapped with the {@see ReflectionMapper}.
 */
interface MapperFactory
{
    /**
     * Called once per class and hydrator, the hydrator wraps the mapper in the middlewares and caches it.
     *
     * The mappers of nested classes are available with {@see StackHydrator::mapper()}. Ask for them once the mapper is
     * used, not inside this method: the hydrator has not cached the mapper which is created at that moment.
     *
     * @param ClassMetadata<T> $metadata
     *
     * @return Mapper<T>|null null if this factory is not responsible for the class
     *
     * @template T of object
     */
    public function create(ClassMetadata $metadata, StackHydrator $hydrator): Mapper|null;
}
