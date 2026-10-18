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
     * @param int|null                                                                   $from      the version of the data the callback expects, see {@see Attribute\UpcasterFor::$from}
     */
    public function __construct(
        public readonly string $className,
        callable $callback,
        public readonly int|null $from = null,
    ) {
        $this->callback = Closure::fromCallable($callback);
    }

    /**
     * @param class-string                                                               $className
     * @param callable(array<string, mixed>, array<string, mixed>): array<string, mixed> $callback
     * @param int|null                                                                   $from      the version of the data the callback expects, see {@see Attribute\UpcasterFor::$from}
     */
    public static function forClass(string $className, callable $callback, int|null $from = null): self
    {
        return new self($className, $callback, $from);
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
