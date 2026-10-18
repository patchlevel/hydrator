<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\NormalizedName;

abstract class AliasParentDto
{
    #[NormalizedName('email', aliases: ['mail'])]
    public string $email = '';

    public string $street = '';
}
