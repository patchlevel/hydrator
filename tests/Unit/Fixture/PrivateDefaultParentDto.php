<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

abstract class PrivateDefaultParentDto
{
    public function __construct(
        private string $note = 'default',
    ) {
    }

    public function note(): string
    {
        return $this->note;
    }
}
