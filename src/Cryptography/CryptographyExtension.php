<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\MetadataEnricherProvider;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\MiddlewareProvider;

final class CryptographyExtension implements MiddlewareProvider, MetadataEnricherProvider
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
