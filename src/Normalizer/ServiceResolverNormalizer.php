<?php

namespace Patchlevel\Hydrator\Normalizer;

use Patchlevel\Hydrator\Hydrator;
use Psr\Container\ContainerInterface;

final class ServiceResolverNormalizer implements Normalizer, HydratorAwareNormalizer
{
    private NormalizerService|null $service = null;

    public function __construct(
        private readonly NormalizerConfig $config,
        private readonly ContainerInterface $container,
    ) {
    }

    public function normalize(mixed $value): mixed
    {
        $service = $this->resolveService();

        return $service->normalize($value, $this->config);
    }

    public function denormalize(mixed $value): mixed
    {
        $service = $this->resolveService();

        return $service->denormalize($value, $this->config);
    }

    private function resolveService(): NormalizerService
    {
        if ($this->service) {
            return $this->service;
        }

        if (!$this->container) {
            throw new \RuntimeException('Container not found');
        }

        $service = $this->container->get($this->config->serviceId());

        if (!$service instanceof NormalizerService) {
            throw new \RuntimeException('Service not found');
        }

        $this->service = $service;

        return $service;
    }

    public function setHydrator(Hydrator $hydrator): void
    {
        $service = $this->resolveService();

        if ($service instanceof HydratorAwareNormalizer) {
            $service->setHydrator($hydrator);
        }
    }
}