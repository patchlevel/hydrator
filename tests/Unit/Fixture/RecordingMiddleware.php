<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use ArrayObject;
use Patchlevel\Hydrator\ClassExtractor;
use Patchlevel\Hydrator\ClassHydrator;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;

final class RecordingMiddleware implements SkippableMiddleware
{
    public int $skipCalls = 0;

    /** @param ArrayObject<int, string> $calls */
    public function __construct(
        private readonly string $name,
        private readonly Skip $skip,
        private readonly ArrayObject $calls,
    ) {
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     * @param ClassHydrator<T>     $next
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context, ClassHydrator $next): object
    {
        $this->calls[] = 'hydrate ' . $this->name;

        return $next->hydrate($data, $context);
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param T                    $object
     * @param array<string, mixed> $context
     * @param ClassExtractor<T>    $next
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, ClassExtractor $next): array
    {
        $this->calls[] = 'extract ' . $this->name;

        return $next->extract($object, $context);
    }

    public function skip(ClassMetadata $metadata): Skip
    {
        $this->skipCalls++;

        return $this->skip;
    }
}
