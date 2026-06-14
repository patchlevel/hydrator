<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast;

use Closure;
use Patchlevel\Hydrator\Metadata\ClassMetadata;

final class CallbackUpcaster implements Upcaster
{
    /** @var Closure(array<string, mixed>, array<string, mixed>): array<string, mixed> */
    private readonly Closure $callback;

    /**
     * @param class-string                                                               $className
     * @param callable(array<string, mixed>, array<string, mixed>): array<string, mixed> $callback
     */
    public function __construct(private readonly string $className, callable $callback)
    {
        $this->callback = Closure::fromCallable($callback);
    }

    /**
     * @param class-string                                                               $className
     * @param callable(array<string, mixed>, array<string, mixed>): array<string, mixed> $callback
     */
    public static function forClass(string $className, callable $callback): self
    {
        return new self($className, $callback);
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function upcast(ClassMetadata $metadata, array $data, array $context): array
    {
        if ($metadata->className !== $this->className) {
            return $data;
        }

        return ($this->callback)($data, $context);
    }
}
