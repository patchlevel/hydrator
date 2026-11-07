<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class CodeBlock implements Block
{
    public function __construct(
        public string $code,
    ) {
    }
}
