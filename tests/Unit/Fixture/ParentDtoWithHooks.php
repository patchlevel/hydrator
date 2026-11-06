<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\Ignore;
use Patchlevel\Hydrator\Attribute\PostHydrate;
use Patchlevel\Hydrator\Attribute\PreExtract;

abstract class ParentDtoWithHooks
{
    /** @var list<string> */
    #[Ignore]
    public array $calls = [];

    #[PostHydrate]
    protected function postHydrate(): void
    {
        $this->calls[] = 'parent postHydrate';
    }

    #[PreExtract]
    private function preExtract(): void
    {
        $this->calls[] = 'parent preExtract';
    }
}
