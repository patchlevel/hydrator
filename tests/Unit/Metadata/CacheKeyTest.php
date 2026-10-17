<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\CacheKey;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheKey::class)]
final class CacheKeyTest extends TestCase
{
    public function testForClass(): void
    {
        self::assertSame(
            'hydrator_metadata_461ebb84f6b57aacd859c578c8ae216c',
            CacheKey::forClass(ProfileCreated::class),
        );
    }
}
