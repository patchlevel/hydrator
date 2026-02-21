<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Cryptography\MissingSubjectId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MissingSubjectId::class)]
final class MissingSubjectIdTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new MissingSubjectId('default');

        self::assertSame('Missing subject id default.', $exception->getMessage());
    }
}
