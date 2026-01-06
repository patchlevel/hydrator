<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Guesser\ChainGuesser;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Normalizer\HydratorAwareNormalizer;
use ReflectionClass;

use function array_key_exists;
use function array_merge;
use function krsort;

use const PHP_VERSION_ID;

final class MetadataHydrator implements Hydrator
{
    /** @var array<class-string, ClassMetadata> */
    private array $classMetadata = [];

    /**
     * @param list<Middleware>       $middlewares
     * @param list<MetadataEnricher> $metadataEnrichers
     */
    public function __construct(
        private readonly MetadataFactory $metadataFactory = new AttributeMetadataFactory(),
        private readonly array $middlewares = [],
        private readonly array $metadataEnrichers = [],
        private readonly bool $defaultLazy = false,
    ) {
    }

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(string $class, array $data, array $context = []): object
    {
        try {
            $metadata = $this->metadata($class);
        } catch (ClassNotFound $e) {
            throw new ClassNotSupported($class, $e);
        }

        if (PHP_VERSION_ID < 80400) {
            $stack = new Stack($this->middlewares);

            return $stack->next()->hydrate($metadata, $data, $context, $stack);
        }

        $lazy = $metadata->lazy ?? $this->defaultLazy;

        if (!$lazy) {
            $stack = new Stack($this->middlewares);

            return $stack->next()->hydrate($metadata, $data, $context, $stack);
        }

        return (new ReflectionClass($class))->newLazyProxy(
            function () use ($metadata, $data, $context): object {
                $stack = new Stack($this->middlewares);

                return $stack->next()->hydrate($metadata, $data, $context, $stack);
            },
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function extract(object $object, array $context = []): array
    {
        $metadata = $this->metadata($object::class);
        $stack = new Stack($this->middlewares);

        return $stack->next()->extract($metadata, $object, $context, $stack);
    }

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    private function metadata(string $class): ClassMetadata
    {
        if (array_key_exists($class, $this->classMetadata)) {
            return $this->classMetadata[$class];
        }

        $this->classMetadata[$class] = $metadata = $this->metadataFactory->metadata($class);

        foreach ($metadata->properties as $property) {
            if (!($property->normalizer instanceof HydratorAwareNormalizer)) {
                continue;
            }

            $property->normalizer->setHydrator($this);
        }

        foreach ($this->metadataEnrichers as $enricher) {
            $enricher->enrich($metadata);
        }

        return $metadata;
    }

    /** @param iterable<MiddlewareProvider|MetadataEnricherProvider|GuesserProvider> $extensions */
    public static function create(
        iterable $extensions = [],
        bool $defaultLazy = false,
    ): self {
        $extensions = [...$extensions, new CoreExtension()];

        $middlewares = [];
        $enrichers = [];
        $guessers = [];

        foreach ($extensions as $extension) {
            if ($extension instanceof MiddlewareProvider) {
                foreach ($extension->middlewares() as $entry) {
                    if ($entry instanceof Middleware) {
                        $middlewares[0][] = $entry;
                    } else {
                        $middlewares[$entry[1] ?? 0][] = $entry[0];
                    }
                }
            }

            if ($extension instanceof MetadataEnricherProvider) {
                foreach ($extension->metadataEnrichers() as $entry) {
                    if ($entry instanceof MetadataEnricher) {
                        $enrichers[0][] = $entry;
                    } else {
                        $enrichers[$entry[1] ?? 0][] = $entry[0];
                    }
                }
            }

            if (!($extension instanceof GuesserProvider)) {
                continue;
            }

            foreach ($extension->guesser() as $entry) {
                if ($entry instanceof Guesser) {
                    $guessers[0][] = $entry;
                } else {
                    $guessers[$entry[1] ?? 0][] = $entry[0];
                }
            }
        }

        krsort($middlewares);
        krsort($enrichers);
        krsort($guessers);

        return new self(
            new AttributeMetadataFactory(
                guesser: new ChainGuesser([...array_merge(...$guessers)]),
            ),
            [...array_merge(...$middlewares)],
            [...array_merge(...$enrichers)],
            $defaultLazy,
        );
    }
}
