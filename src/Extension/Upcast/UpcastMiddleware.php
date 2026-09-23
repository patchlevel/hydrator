<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;

final readonly class UpcastMiddleware implements SkippableMiddleware
{
    /** @param list<Upcaster> $upcasters */
    public function __construct(
        private array $upcasters,
    ) {
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
        foreach ($this->upcasters as $upcaster) {
            $data = $upcaster->upcast($metadata, $data, $context);
        }

        return $stack->next()->hydrate($metadata, $data, $context, $stack);
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        return $stack->next()->extract($metadata, $object, $context, $stack);
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public function skip(ClassMetadata $metadata): Skip
    {
        // upcasting only ever happens while hydrating, extract() never does anything
        if ($this->upcasters === []) {
            return Skip::Both;
        }

        foreach ($this->upcasters as $upcaster) {
            // an upcaster we cannot introspect might still target this class
            if (!$upcaster instanceof CallbackUpcaster || $upcaster->className === $metadata->className) {
                return Skip::Extract;
            }
        }

        // every upcaster is a CallbackUpcaster and none of them targets this class
        return Skip::Both;
    }
}
