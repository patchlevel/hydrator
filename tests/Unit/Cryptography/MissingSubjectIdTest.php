<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Cryptography\MissingSubjectId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\Hydrator\Cryptography\MissingSubjectId */
final class MissingSubjectIdTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new MissingSubjectId(ProfileCreated::class, 'profile_id');

        self::assertSame('Missing subject id for Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated in field profile_id.', $exception->getMessage());
    }
}
