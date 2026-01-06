<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Middleware\Middleware;

final class CryptographyExtension extends Extension
{
    public function __construct(
        private readonly PayloadCryptographer $cryptography,
    ) {
    }

    /** @return iterable<Middleware|array{0: Middleware, 1?: int}> */
    public function middlewares(): iterable
    {
        yield [new CryptographyMiddleware($this->cryptography), 64];
    }

    /** @return iterable<MetadataEnricher|array{0: MetadataEnricher, 1?: int}> */
    public function metadataEnrichers(): iterable
    {
        yield [new CryptographyMetadataEnricher(), 64];
    }
}
