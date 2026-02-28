<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Lifecycle;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;

use function assert;

/** @experimental */
final class LifecycleMiddleware implements Middleware
{
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
        $lifecycle = $metadata->extras[Lifecycle::class] ?? null;
        assert($lifecycle instanceof Lifecycle || $lifecycle === null);

        if ($lifecycle?->preHydrate) {
            $data = $metadata->reflection->getMethod($lifecycle->preHydrate)->invoke(null, $data, $context);
            /** @var array<string, mixed> $data */
        }

        $object = $stack->next()->hydrate($metadata, $data, $context, $stack);

        if ($lifecycle?->postHydrate) {
            $metadata->reflection->getMethod($lifecycle->postHydrate)->invoke(null, $object, $context);
        }

        return $object;
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
        $lifecycle = $metadata->extras[Lifecycle::class] ?? null;
        assert($lifecycle instanceof Lifecycle || $lifecycle === null);

        if ($lifecycle?->preExtract) {
            $metadata->reflection->getMethod($lifecycle->preExtract)->invoke(null, $object, $context);
        }

        $data = $stack->next()->extract($metadata, $object, $context, $stack);

        if ($lifecycle?->postExtract) {
            $data = $metadata->reflection->getMethod($lifecycle->postExtract)->invoke(null, $data, $context);
            /** @var array<string, mixed> $data */
        }

        return $data;
    }
}
