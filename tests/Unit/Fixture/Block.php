<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\ObjectMapNormalizer;

#[ObjectMapNormalizer([ContentBlock::class => 'content', CodeBlock::class => 'code'])]
interface Block
{
}
