<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

/** @experimental */
final readonly class UpcastExtension implements Extension
{
    /**
     * @param list<Upcaster> $beforeEncoding  upcasters that reshape the raw stored payload before its values are decoded
     * @param list<Upcaster> $beforeTransform upcasters that run after value decoding, right before the object is built
     */
    public function __construct(
        private array $beforeEncoding = [],
        private array $beforeTransform = [],
    ) {
    }

    public function configure(StackHydratorBuilder $builder): void
    {
        if ($this->beforeEncoding !== []) {
            $builder->addMiddleware(
                new UpcastMiddleware($this->beforeEncoding),
                Extension::PRIORITY_BEFORE_ENCODING,
            );
        }

        if ($this->beforeTransform === []) {
            return;
        }

        $builder->addMiddleware(
            new UpcastMiddleware($this->beforeTransform),
            Extension::PRIORITY_BEFORE_TRANSFORM,
        );
    }
}
