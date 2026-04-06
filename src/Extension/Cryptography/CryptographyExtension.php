<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Cryptography;

use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

/** @experimental */
final class CryptographyExtension implements Extension
{
    public function __construct(
        private readonly Cryptographer $cryptography,
        private readonly PayloadCryptographer|null $legacyCryptographer = null,
        private readonly bool $legacyMetadataMapping = false,
    ) {
    }

    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addMetadataEnricher(new CryptographyMetadataEnricher(), 64);
        $builder->addMiddleware(new CryptographyMiddleware($this->cryptography), 64);

        if ($this->legacyMetadataMapping) {
            $builder->addMetadataEnricher(new LegacyCryptographyMetadataEnricher(), 63);
        }

        if ($this->legacyCryptographer === null) {
            return;
        }

        $builder->addMiddleware(new LegacyCryptographyDecryptMiddleware($this->legacyCryptographer), 65);
    }
}
