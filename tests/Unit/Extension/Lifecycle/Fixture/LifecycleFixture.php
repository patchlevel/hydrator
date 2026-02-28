<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Lifecycle\Fixture;

use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PostExtract;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PostHydrate;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PreExtract;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PreHydrate;

use function assert;
use function is_string;

final class LifecycleFixture
{
    public function __construct(public string $name)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    #[PreHydrate]
    public static function preHydrate(array $data, array $context): array
    {
        $name = $data['name'] ?? '';
        assert(is_string($name));

        $data['name'] = $name . ' [preHydrate]';

        return $data;
    }

    /** @param array<string, mixed> $context */
    #[PostHydrate]
    public static function postHydrate(object $object, array $context): void
    {
        if (!($object instanceof self)) {
            return;
        }

        $object->name .= ' [postHydrate]';
    }

    /** @param array<string, mixed> $context */
    #[PreExtract]
    public static function preExtract(object $object, array $context): void
    {
        if (!($object instanceof self)) {
            return;
        }

        $object->name .= ' [preExtract]';
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    #[PostExtract]
    public static function postExtract(array $data, array $context): array
    {
        $name = $data['name'] ?? '';
        assert(is_string($name));

        $data['name'] = $name . ' [postExtract]';

        return $data;
    }
}
