<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Middleware;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\Transformer\CallStack;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;

use function assert;

/**
 * Turns the array into the object and back with the {@see ClassTransformer} which the hydrator provides for the class.
 * The StackHydrator ends every stack with it, it does not have to be registered. Without a hydrator, for example in
 * a test of a middleware, reflection is used.
 */
final class TransformMiddleware implements Middleware, HydratorAwareMiddleware
{
    private StackHydrator|null $hydrator = null;

    /** @var array<class-string, ClassTransformer> only used without a hydrator */
    private array $transformers = [];

    private readonly CallStack $callStack;

    public function __construct()
    {
        $this->callStack = new CallStack();
    }

    public function setHydrator(StackHydrator $hydrator): void
    {
        $this->hydrator = $hydrator;
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
    {
        $object = $this->transformer($metadata)->hydrate($data, $context);
        assert($object instanceof $metadata->className);

        return $object;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        return $this->transformer($metadata)->extract($object, $context);
    }

    private function transformer(ClassMetadata $metadata): ClassTransformer
    {
        if ($this->hydrator !== null) {
            return $this->hydrator->transformer($metadata);
        }

        return $this->transformers[$metadata->className] ??= new ReflectionTransformer($metadata, $this->callStack);
    }
}
