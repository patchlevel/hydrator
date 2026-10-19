<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\NormalizedName;

/** Same shape as the other Fingerprint* fixtures, used to simulate a class changing after code generation. */
final class FingerprintChangedAliasDto
{
    public function __construct(
        #[NormalizedName('name', aliases: ['title'])]
        public string $name,
        public string $text = 'a',
    ) {
    }

    public function text(): string
    {
        return $this->text;
    }
}
