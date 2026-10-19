<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\NormalizedName;

final class AliasDto
{
    public function __construct(
        #[EmailNormalizer]
        #[NormalizedName('email', aliases: ['mail', 'e_mail'])]
        public Email $email,
        #[NormalizedName('name', aliases: ['username'])]
        public string $name = 'unknown',
    ) {
    }
}
