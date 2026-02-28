<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Lifecycle;

use LogicException;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PostExtract;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PostHydrate;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PreExtract;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PreHydrate;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;

use function sprintf;

/** @experimental */
final class LifecycleMetadataEnricher implements MetadataEnricher
{
    public function enrich(ClassMetadata $classMetadata): void
    {
        $preHydrate = null;
        $postHydrate = null;
        $preExtract = null;
        $postExtract = null;

        foreach ($classMetadata->reflection->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->getAttributes(PreHydrate::class)) {
                if ($reflectionMethod->isStatic() === false) {
                    throw new LogicException(sprintf('Method "%s::%s" must be static when using the PreHydrate attribute.', $classMetadata->className, $reflectionMethod->getName()));
                }

                $preHydrate = $reflectionMethod->getName();
            }

            if ($reflectionMethod->getAttributes(PostHydrate::class)) {
                if ($reflectionMethod->isStatic() === false) {
                    throw new LogicException(sprintf('Method "%s::%s" must be static when using the PostHydrate attribute.', $classMetadata->className, $reflectionMethod->getName()));
                }

                $postHydrate = $reflectionMethod->getName();
            }

            if ($reflectionMethod->getAttributes(PreExtract::class)) {
                if ($reflectionMethod->isStatic() === false) {
                    throw new LogicException(sprintf('Method "%s::%s" must be static when using the PreExtract attribute.', $classMetadata->className, $reflectionMethod->getName()));
                }

                $preExtract = $reflectionMethod->getName();
            }

            if (!$reflectionMethod->getAttributes(PostExtract::class)) {
                continue;
            }

            if ($reflectionMethod->isStatic() === false) {
                throw new LogicException(sprintf('Method "%s::%s" must be static when using the PostExtract attribute.', $classMetadata->className, $reflectionMethod->getName()));
            }

            $postExtract = $reflectionMethod->getName();
        }

        if ($preHydrate === null && $postHydrate === null && $preExtract === null && $postExtract === null) {
            return;
        }

        $classMetadata->extras[Lifecycle::class] = new Lifecycle(
            $preHydrate,
            $postHydrate,
            $preExtract,
            $postExtract,
        );
    }
}
