<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Handler;

use Patchlevel\Hydrator\ArrayDataRequired;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Next;

use function is_array;

/**
 * Middlewares run for the class, the chain of the middlewares which do not skip the class is built once.
 *
 * @internal
 */
final class MiddlewareHandler implements HydrateHandler, ExtractHandler
{
    /** @param ClassMetadata<object> $metadata */
    public function __construct(
        private readonly ClassMetadata $metadata,
        private readonly Next $next,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function hydrate(mixed $data, array $context): object
    {
        if (!is_array($data)) {
            throw new ArrayDataRequired($this->metadata->className);
        }

        return $this->next->hydrate($this->metadata, $data, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context): array
    {
        return $this->next->extract($this->metadata, $object, $context);
    }
}
