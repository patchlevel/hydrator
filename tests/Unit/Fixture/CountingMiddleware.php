<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;

final class CountingMiddleware implements SkippableMiddleware
{
    /** @param array<class-string, Skip> $skip */
    public function __construct(
        private readonly array $skip = [],
    ) {
    }

    /** @var array<class-string, int> */
    public array $hydrated = [];

    /** @var array<class-string, int> */
    public array $extracted = [];

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
        $this->hydrated[$metadata->className] = ($this->hydrated[$metadata->className] ?? 0) + 1;

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
        $this->extracted[$metadata->className] = ($this->extracted[$metadata->className] ?? 0) + 1;

        return $stack->next()->extract($metadata, $object, $context, $stack);
    }

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @template T of object
     */
    public function skip(ClassMetadata $metadata): Skip
    {
        return $this->skip[$metadata->className] ?? Skip::None;
    }
}
