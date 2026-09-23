<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast;

use Patchlevel\Hydrator\Extension\Upcast\Attribute\UpcasterFor;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;
use ReflectionClass;

use function array_map;

final readonly class UpcastMiddleware implements SkippableMiddleware
{
    /** @var list<class-string|null> */
    private array $targets;

    /** @param list<Upcaster> $upcasters */
    public function __construct(
        private array $upcasters,
    ) {
        $this->targets = array_map(self::resolveTarget(...), $upcasters);
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
        foreach ($this->upcasters as $index => $upcaster) {
            $target = $this->targets[$index];

            if ($target !== null && $target !== $metadata->className) {
                continue;
            }

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

        foreach ($this->targets as $target) {
            // a target we could not resolve might still apply to this class
            if ($target === null || $target === $metadata->className) {
                return Skip::Extract;
            }
        }

        // none of the upcasters targets this class
        return Skip::Both;
    }

    /**
     * Resolve the class an upcaster is restricted to, either from its
     * `#[UpcasterFor]` attribute or, for a `CallbackUpcaster`, from the class
     * name it was built with. Null means the upcaster applies to every class.
     *
     * @return class-string|null
     */
    private static function resolveTarget(Upcaster $upcaster): string|null
    {
        if ($upcaster instanceof CallbackUpcaster) {
            return $upcaster->className;
        }

        $attributes = (new ReflectionClass($upcaster))->getAttributes(UpcasterFor::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance()->className;
    }
}
