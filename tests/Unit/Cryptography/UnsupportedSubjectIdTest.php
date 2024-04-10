<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Cryptography\UnsupportedSubjectId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\Hydrator\Cryptography\UnsupportedSubjectId */
final class UnsupportedSubjectIdTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new UnsupportedSubjectId('Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated', 'profile_id', 42.4);

        self::assertSame('Unsupported subject id for Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated in field profile_id. Got float.', $exception->getMessage());
    }
}
