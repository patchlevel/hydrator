<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit;

use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;

final class PropertyHooks
{
    public ProfileId $profileId;

    #[EmailNormalizer]
    public Email $email;
}
