<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

final class CryptographyExtension implements Extension
{
    public function __construct(
        private readonly Cryptographer $cryptography,
    ) {
    }

    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addMetadataEnricher(new CryptographyMetadataEnricher(), 64);
        $builder->addMiddleware(new CryptographyMiddleware($this->cryptography), 64);
    }
}
