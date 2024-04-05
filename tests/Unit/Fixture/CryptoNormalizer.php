<?php

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Attribute;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\NormalizerConfig;

#[Attribute(Attribute::TARGET_CLASS)]
class CryptoNormalizer implements NormalizerConfig
{
    public function __construct(
        public readonly Normalizer|null $normalizer,
        public readonly mixed $fallback = null,
    ) {}

    public function serviceId(): string
    {
        return CryptoNormalizerService::class;
    }
}