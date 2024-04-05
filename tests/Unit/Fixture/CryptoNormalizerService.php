<?php

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\NormalizerConfig;
use Patchlevel\Hydrator\Normalizer\NormalizerService;

class CryptoNormalizerService implements NormalizerService
{
    public function __construct(
        private readonly Crypto $crypto,
    ) {
    }

    public function normalize(mixed $value, NormalizerConfig $config): mixed
    {
        if ($config->normalizer) {
            $value = $config->normalizer->normalize($value);
        }

        return $this->crypto->encrypt($value);
    }

    public function denormalize(mixed $value, NormalizerConfig $config): mixed
    {
        try {
            $value = $this->crypto->decrypt($value);

            if ($config->normalizer) {
                return $config->normalizer->denormalize($value);
            }

            return $value;
        } catch (\Exception $e) {
            return $config->fallback;
        }
    }
}