<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Cryptography\MissingSubjectId;
use Patchlevel\Hydrator\Cryptography\SubjectIds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubjectIds::class)]
final class SubjectIdsTest extends TestCase
{
    public function testConstruct(): void
    {
        $subjectIds = new SubjectIds(['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $subjectIds->subjectIds);
    }

    public function testGet(): void
    {
        $subjectIds = new SubjectIds(['foo' => 'bar']);

        self::assertSame('bar', $subjectIds->get('foo'));
    }

    public function testGetMissing(): void
    {
        $this->expectException(MissingSubjectId::class);
        $this->expectExceptionMessage('Missing subject id foo.');

        $subjectIds = new SubjectIds();
        $subjectIds->get('foo');
    }

    public function testMerge(): void
    {
        $subjectIds1 = new SubjectIds(['foo' => 'bar']);
        $subjectIds2 = new SubjectIds(['baz' => 'qux']);

        $merged = $subjectIds1->merge($subjectIds2);

        self::assertSame(['foo' => 'bar', 'baz' => 'qux'], $merged->subjectIds);
        self::assertNotSame($subjectIds1, $merged);
        self::assertNotSame($subjectIds2, $merged);
    }

    public function testMergeOverwrite(): void
    {
        $subjectIds1 = new SubjectIds(['foo' => 'bar']);
        $subjectIds2 = new SubjectIds(['foo' => 'baz']);

        $merged = $subjectIds1->merge($subjectIds2);

        self::assertSame(['foo' => 'baz'], $merged->subjectIds);
    }
}
