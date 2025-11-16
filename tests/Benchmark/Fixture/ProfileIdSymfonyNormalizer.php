<?php

namespace Patchlevel\Hydrator\Tests\Benchmark\Fixture;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ProfileIdSymfonyNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return ProfileId::fromString($data);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === ProfileId::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ProfileId::class => true];
    }

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|\ArrayObject|null {
        return $data->toString();
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ProfileId;
    }
}