<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups;

use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\Extension\Generated\FilteredMapper;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperWarmer;
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Mapper\ReflectionMapper;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\AddressFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\ChildFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\ParentFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function glob;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/** Groups behave the same with the reflection mappers and the generated mappers. */
#[CoversClass(GroupsExtension::class)]
final class GroupsExtensionTest extends TestCase
{
    private static string $cachePath;

    public static function setUpBeforeClass(): void
    {
        self::$cachePath = sys_get_temp_dir() . '/' . uniqid('patchlevel-hydrator-groups-', true);

        (new GeneratedMapperWarmer(self::builder(false)->metadataFactory(), self::$cachePath))->warmup([
            UserFixture::class,
            ParentFixture::class,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        foreach (glob(self::$cachePath . '/*.php') ?: [] as $file) {
            unlink($file);
        }

        rmdir(self::$cachePath);
    }

    /** @return iterable<string, array{bool}> */
    public static function provideGenerated(): iterable
    {
        yield 'reflection' => [false];
        yield 'generated' => [true];
    }

    #[DataProvider('provideGenerated')]
    public function testMappers(bool $generated): void
    {
        $hydrator = self::hydrator($generated);

        foreach ([UserFixture::class, AddressFixture::class, ParentFixture::class, ChildFixture::class] as $class) {
            $mapper = $hydrator->mapper($class);

            if (!$generated) {
                self::assertInstanceOf(ReflectionMapper::class, $mapper);

                continue;
            }

            self::assertInstanceOf(FilteredMapper::class, $mapper);
        }
    }

    #[DataProvider('provideGenerated')]
    public function testExtractWithoutGroups(bool $generated): void
    {
        self::assertSame(
            [
                'id' => '1',
                'name' => 'John',
                'email' => 'john@example.com',
                'address' => ['city' => 'Berlin', 'street' => 'Main Street'],
                'internal' => 'secret',
            ],
            self::hydrator($generated)->extract($this->user()),
        );
    }

    #[DataProvider('provideGenerated')]
    public function testExtractWithGroup(bool $generated): void
    {
        self::assertSame(
            [
                'id' => '1',
                'name' => 'John',
                'address' => ['city' => 'Berlin'],
            ],
            self::hydrator($generated)->extract($this->user(), [GroupsExtension::GROUPS => 'public']),
        );
    }

    #[DataProvider('provideGenerated')]
    public function testExtractWithMultipleGroups(bool $generated): void
    {
        self::assertSame(
            [
                'id' => '1',
                'name' => 'John',
                'email' => 'john@example.com',
                'address' => ['city' => 'Berlin', 'street' => 'Main Street'],
            ],
            self::hydrator($generated)->extract($this->user(), [GroupsExtension::GROUPS => ['public', 'admin']]),
        );
    }

    #[DataProvider('provideGenerated')]
    public function testExtractWithAllGroup(bool $generated): void
    {
        self::assertSame(
            [
                'id' => '1',
                'name' => 'John',
                'email' => 'john@example.com',
                'address' => ['city' => 'Berlin', 'street' => 'Main Street'],
                'internal' => 'secret',
            ],
            self::hydrator($generated)->extract($this->user(), [GroupsExtension::GROUPS => GroupsExtension::ALL]),
        );
    }

    #[DataProvider('provideGenerated')]
    public function testExtractWithIgnoredGroup(bool $generated): void
    {
        self::assertSame(
            [
                'name' => 'John',
                'internal' => 'secret',
            ],
            self::hydrator($generated)->extract($this->user(), [GroupsExtension::IGNORED_GROUPS => 'admin']),
        );
    }

    #[DataProvider('provideGenerated')]
    public function testHydrateWithGroup(bool $generated): void
    {
        $user = self::hydrator($generated)->hydrate(
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

    #[DataProvider('provideGenerated')]
    public function testHydrateWithObjectToPopulate(bool $generated): void
    {
        $user = $this->user();

        $result = self::hydrator($generated)->hydrate(
            UserFixture::class,
            ['name' => 'Jane', 'email' => 'jane@example.com'],
            [GroupsExtension::GROUPS => 'admin', Hydrator::OBJECT_TO_POPULATE => $user],
        );

        self::assertSame($user, $result);
        self::assertSame('1', $user->id);
        self::assertSame('John', $user->name);
        self::assertSame('jane@example.com', $user->email);
        self::assertSame('Berlin', $user->address->city);
        self::assertSame('secret', $user->internal);
    }

    #[DataProvider('provideGenerated')]
    public function testExtractHydratedObjectWithTheSameGroup(bool $generated): void
    {
        $hydrator = self::hydrator($generated);
        $data = [
            'id' => '1',
            'name' => 'John',
            'address' => ['city' => 'Berlin'],
        ];

        // the properties of other groups are not initialized and must not be read
        $user = $hydrator->hydrate(UserFixture::class, $data, [GroupsExtension::GROUPS => 'public']);

        self::assertSame($data, $hydrator->extract($user, [GroupsExtension::GROUPS => 'public']));
    }

    #[DataProvider('provideGenerated')]
    public function testGroupsBreakCircularReference(bool $generated): void
    {
        $hydrator = self::hydrator($generated);

        $parent = new ParentFixture('parent');
        $parent->child = new ChildFixture('child', $parent);

        self::assertSame(
            ['name' => 'parent', 'child' => ['name' => 'child']],
            $hydrator->extract($parent, [GroupsExtension::GROUPS => 'parent']),
        );

        self::assertSame(
            ['name' => 'child', 'parent' => ['name' => 'parent']],
            $hydrator->extract($parent->child, [GroupsExtension::GROUPS => 'child']),
        );
    }

    #[DataProvider('provideGenerated')]
    public function testCircularReferenceWithoutGroups(bool $generated): void
    {
        $parent = new ParentFixture('parent');
        $parent->child = new ChildFixture('child', $parent);

        $this->expectException(CircularReference::class);

        self::hydrator($generated)->extract($parent);
    }

    #[DataProvider('provideGenerated')]
    public function testCircularReferenceWithAllGroup(bool $generated): void
    {
        $parent = new ParentFixture('parent');
        $parent->child = new ChildFixture('child', $parent);

        $this->expectException(CircularReference::class);

        self::hydrator($generated)->extract($parent, [GroupsExtension::GROUPS => GroupsExtension::ALL]);
    }

    private static function hydrator(bool $generated): StackHydrator
    {
        return self::builder($generated)->build();
    }

    private static function builder(bool $generated): StackHydratorBuilder
    {
        $builder = (new StackHydratorBuilder())->useExtension(new GroupsExtension());

        if ($generated) {
            $builder->useExtension(new GeneratedMapperExtension(self::$cachePath));
        }

        return $builder;
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
