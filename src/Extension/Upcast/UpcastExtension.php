<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

final readonly class UpcastExtension implements Extension
{
    /**
     * Context key with the version of the hydrated data by class, for example [ProfileCreated::class => 1]. Versioned
     * upcasters only run for data of their version or older, without a version the data is treated as up to date.
     */
    public const VERSIONS = 'upcast_versions';

    /**
     * @param list<Upcaster> $beforeEncoding upcasters that reshape the raw stored payload before its values are decoded
     * @param list<Upcaster> $beforeMapping  upcasters that run after value decoding, right before the object is built
     */
    public function __construct(
        private array $beforeEncoding = [],
        private array $beforeMapping = [],
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

        if ($this->beforeMapping === []) {
            return;
        }

        $builder->addMiddleware(
            new UpcastMiddleware($this->beforeMapping),
            Extension::PRIORITY_BEFORE_MAPPING,
        );
    }
}
