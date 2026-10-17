<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

/** Same shape as the other Fingerprint* fixtures, used to simulate a class changing after code generation. */
final class FingerprintChangedDefaultDto
{
    public function __construct(
        public string $name,
        public string $text = 'b',
    ) {
    }

    public function text(): string
    {
        return $this->text;
    }
}
