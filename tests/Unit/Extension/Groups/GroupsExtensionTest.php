<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups;

use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\AddressFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\ChildFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\ParentFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupsExtension::class)]
final class GroupsExtensionTest extends TestCase
{
    public function testExtractWithoutGroups(): void
    {
        self::assertSame(
            [
                'id' => '1',
                'name' => 'John',
                'email' => 'john@example.com',
                'address' => ['city' => 'Berlin', 'street' => 'Main Street'],
                'internal' => 'secret',
            ],
            $this->hydrator()->extract($this->user()),
        );
    }

    public function testExtractWithGroup(): void
    {
        self::assertSame(
            [
                'id' => '1',
                'name' => 'John',
                'address' => ['city' => 'Berlin'],
            ],
            $this->hydrator()->extract($this->user(), [GroupsExtension::GROUPS => 'public']),
        );
    }

    public function testExtractWithMultipleGroups(): void
    {
        self::assertSame(
            [
                'id' => '1',
                'name' => 'John',
                'email' => 'john@example.com',
                'address' => ['city' => 'Berlin', 'street' => 'Main Street'],
            ],
            $this->hydrator()->extract($this->user(), [GroupsExtension::GROUPS => ['public', 'admin']]),
        );
    }

    public function testExtractWithAllGroup(): void
    {
        self::assertSame(
            [
                'id' => '1',
                'name' => 'John',
                'email' => 'john@example.com',
                'address' => ['city' => 'Berlin', 'street' => 'Main Street'],
                'internal' => 'secret',
            ],
            $this->hydrator()->extract($this->user(), [GroupsExtension::GROUPS => GroupsExtension::ALL]),
        );
    }

    public function testExtractWithIgnoredGroup(): void
    {
        self::assertSame(
            [
                'name' => 'John',
                'internal' => 'secret',
            ],
            $this->hydrator()->extract($this->user(), [GroupsExtension::IGNORED_GROUPS => 'admin']),
        );
    }

    public function testHydrateWithGroup(): void
    {
        $user = $this->hydrator()->hydrate(
            UserFixture::class,
            [
                'id' => '1',
                'name' => 'John',
                'email' => 'john@example.com',
                'address' => ['city' => 'Berlin', 'street' => 'Main Street'],
                'internal' => 'secret',
            ],
            [GroupsExtension::GROUPS => ['public']],
        );

        self::assertSame('1', $user->id);
        self::assertSame('John', $user->name);
        self::assertSame('Berlin', $user->address->city);
        self::assertSame('default', $user->internal);
        self::assertFalse(isset($user->email));
        self::assertFalse(isset($user->address->street));
    }

    public function testGroupsBreakCircularReference(): void
    {
        $parent = new ParentFixture('parent');
        $parent->child = new ChildFixture('child', $parent);

        self::assertSame(
            ['name' => 'parent', 'child' => ['name' => 'child']],
            $this->hydrator()->extract($parent, [GroupsExtension::GROUPS => 'parent']),
        );

        self::assertSame(
            ['name' => 'child', 'parent' => ['name' => 'parent']],
            $this->hydrator()->extract($parent->child, [GroupsExtension::GROUPS => 'child']),
        );
    }

    public function testCircularReferenceWithoutGroups(): void
    {
        $parent = new ParentFixture('parent');
        $parent->child = new ChildFixture('child', $parent);

        $this->expectException(CircularReference::class);

        $this->hydrator()->extract($parent);
    }

    private function hydrator(): StackHydrator
    {
        return (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new GroupsExtension())
            ->build();
    }

    private function user(): UserFixture
    {
        return new UserFixture(
            '1',
            'John',
            'john@example.com',
            new AddressFixture('Berlin', 'Main Street'),
            'secret',
        );
    }
}
