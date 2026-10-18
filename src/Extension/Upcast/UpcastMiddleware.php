<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast;

use Patchlevel\Hydrator\Extension\Upcast\Attribute\UpcasterFor;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use ReflectionClass;

use function is_array;
use function is_int;

final readonly class UpcastMiddleware implements SkippableMiddleware
{
    /** @var list<class-string|null> */
    private array $targets;

    /** @var list<int|null> the version of the data each upcaster expects, null for data of every version */
    private array $versions;

    private bool $versioned;

    /**
     * @param list<Upcaster> $upcasters
     *
     * @throws UpcastersNotInVersionOrder if a versioned upcaster comes after one of a newer version of the same class.
     */
    public function __construct(
        private array $upcasters,
    ) {
        $targets = [];
        $versions = [];

        /** @var array<class-string, int> $previous */
        $previous = [];

        foreach ($upcasters as $upcaster) {
            [$target, $version] = self::resolveTarget($upcaster);
            $targets[] = $target;
            $versions[] = $version;

            if ($target === null || $version === null) {
                continue;
            }

            if (isset($previous[$target]) && $version < $previous[$target]) {
                throw new UpcastersNotInVersionOrder($target, $previous[$target], $version);
            }

            $previous[$target] = $version;
        }

        $this->targets = $targets;
        $this->versions = $versions;
        $this->versioned = $previous !== [];
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     * @param Mapper<T>            $next
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Mapper $next): object
    {
        $version = $this->versioned ? self::version($metadata, $context) : null;

        foreach ($this->upcasters as $index => $upcaster) {
            $target = $this->targets[$index];

            if ($target !== null && $target !== $metadata->className) {
                continue;
            }

            $from = $this->versions[$index];

            // the data is already newer than the version the upcaster expects, or up to date without a version
            if ($from !== null && ($version === null || $from < $version)) {
                continue;
            }

            $data = $upcaster->upcast($metadata, $data, $context);
        }

        return $next->hydrate($data, $context);
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param T                    $object
     * @param array<string, mixed> $context
     * @param Mapper<T>            $next
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, Mapper $next): array
    {
        return $next->extract($object, $context);
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
     * Resolve the class an upcaster is restricted to and the version of the data
     * it expects, either from its `#[UpcasterFor]` attribute or, for a
     * `CallbackUpcaster`, from the values it was built with. A null class means
     * the upcaster applies to every class, a null version to data of every version.
     *
     * @return array{class-string|null, int|null}
     */
    private static function resolveTarget(Upcaster $upcaster): array
    {
        if ($upcaster instanceof CallbackUpcaster) {
            return [$upcaster->className, $upcaster->from];
        }

        $attributes = (new ReflectionClass($upcaster))->getAttributes(UpcasterFor::class);

        if ($attributes === []) {
            return [null, null];
        }

        $attribute = $attributes[0]->newInstance();

        return [$attribute->className, $attribute->from];
    }

    /**
     * The version of the hydrated data, see {@see UpcastExtension::VERSIONS}.
     *
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $context
     *
     * @template T of object
     */
    private static function version(ClassMetadata $metadata, array $context): int|null
    {
        $versions = $context[UpcastExtension::VERSIONS] ?? null;

        if (!is_array($versions)) {
            return null;
        }

        $version = $versions[$metadata->className] ?? null;

        return is_int($version) ? $version : null;
    }
}
