<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ClassTransformerFactory;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;

use function in_array;

/** Creates reflection transformers for the given classes and records every call. */
final class RecordingTransformerFactory implements ClassTransformerFactory
{
    /** @var list<class-string> */
    public array $created = [];

    /** @var list<string> */
    public array $calls = [];

    /** @param list<class-string> $classes */
    public function __construct(
        private readonly array $classes,
    ) {
    }

    public function create(ClassMetadata $metadata, StackHydrator $hydrator): ClassTransformer|null
    {
        if (!in_array($metadata->className, $this->classes, true)) {
            return null;
        }

        $this->created[] = $metadata->className;
        $inner = new ReflectionTransformer($metadata, $hydrator->callStack());

        return new class ($inner, $this) implements ClassTransformer {
            public function __construct(
                private readonly ClassTransformer $inner,
                private readonly RecordingTransformerFactory $factory,
            ) {
            }

            /**
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             */
            public function hydrate(array $data, array $context): object
            {
                $object = $this->inner->hydrate($data, $context);
                $this->factory->calls[] = 'hydrate ' . $object::class;

                return $object;
            }

            /**
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(object $object, array $context): array
            {
                $this->factory->calls[] = 'extract ' . $object::class;

                return $this->inner->extract($object, $context);
            }
        };
    }
}
