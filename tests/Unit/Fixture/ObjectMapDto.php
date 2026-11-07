<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class ObjectMapDto
{
    /** @param list<Block> $blocks */
    public function __construct(
        public Block $block,
        public ContentBlock $contentBlock,
        public array $blocks,
        public Shape $shape,
    ) {
    }
}
