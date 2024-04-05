<?php

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

class Crypto
{
    public function encrypt(mixed $value): string
    {
        return $value;
    }

    public function decrypt(string $value): mixed
    {
        return $value;
    }
}