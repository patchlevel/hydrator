<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class DefaultDto
{
    public bool $admin = true;

    public function __construct(
        public string $name,
        public Email $email = new Email('info@patchlevel.de'),
    ) {
    }
}
