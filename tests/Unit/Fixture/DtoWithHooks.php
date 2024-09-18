<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\PostHydrate;
use Patchlevel\Hydrator\Attribute\PreExtract;

final class DtoWithHooks
{
    public bool $postHydrateCalled = false;

    public bool $preExtractCalled = false;

    #[PostHydrate]
    private function postHydrate(): void
    {
        $this->postHydrateCalled = true;
    }

    #[PreExtract]
    private function preExtract(): void
    {
        $this->preExtractCalled = true;
    }
}
