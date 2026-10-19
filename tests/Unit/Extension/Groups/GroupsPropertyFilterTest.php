<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups;

use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;
use Patchlevel\Hydrator\Extension\Groups\GroupsMetadataEnricher;
use Patchlevel\Hydrator\Extension\Groups\GroupsPropertyFilter;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\NoGroupsFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_keys;

#[CoversClass(GroupsPropertyFilter::class)]
final class GroupsPropertyFilterTest extends TestCase
{
    /** @return iterable<string, array{mixed, list<string>|null}> */
    public static function provideGroups(): iterable
    {
        yield 'no groups' => [null, null];
        yield 'empty list' => [[], null];
        yield 'invalid value' => [42, null];
        yield 'only invalid entries' => [[42, null], null];
        yield 'string' => ['admin', ['id', 'email', 'address']];
        yield 'list' => [['public'], ['id', 'name', 'address']];
        yield 'invalid entries are ignored' => [['public', 42], ['id', 'name', 'address']];
        yield 'unknown group' => [['unknown'], []];
        yield 'all' => [['unknown', GroupsExtension::ALL], null];
    }

    /** @param list<string>|null $expectedFields null if no property is filtered out */
    #[DataProvider('provideGroups')]
    public function testGroups(mixed $groups, array|null $expectedFields): void
    {
        $fields = (new GroupsPropertyFilter())->filter(
            $this->metadata(UserFixture::class),
            [GroupsExtension::GROUPS => $groups],
        );

        self::assertSame($expectedFields, $fields === null ? null : array_keys($fields));
    }

    /** @return iterable<string, array{mixed, mixed, list<string>|null}> */
    public static function provideIgnoredGroups(): iterable
    {
        yield 'string' => [null, 'admin', ['name', 'internal']];
        yield 'list' => [null, ['admin', 'public'], ['internal']];
        yield 'unknown group' => [null, ['unknown'], null];
        yield 'empty list' => [null, [], null];
        yield 'with groups' => ['public', 'admin', ['name']];
        yield 'with all' => [GroupsExtension::ALL, 'admin', ['name', 'internal']];
    }

    /** @param list<string>|null $expectedFields null if no property is filtered out */
    #[DataProvider('provideIgnoredGroups')]
    public function testIgnoredGroups(mixed $groups, mixed $ignoredGroups, array|null $expectedFields): void
    {
        $fields = (new GroupsPropertyFilter())->filter(
            $this->metadata(UserFixture::class),
            [
                GroupsExtension::GROUPS => $groups,
                GroupsExtension::IGNORED_GROUPS => $ignoredGroups,
            ],
        );

        self::assertSame($expectedFields, $fields === null ? null : array_keys($fields));
    }

    /** @return iterable<string, array{mixed, mixed, list<string>|null}> */
    public static function provideClassWithoutGroups(): iterable
    {
        yield 'groups' => ['public', null, []];
        yield 'all' => [GroupsExtension::ALL, null, null];
        yield 'ignored groups' => [null, 'admin', null];
        yield 'groups and ignored groups' => ['public', 'admin', []];
    }

    /** @param list<string>|null $expectedFields null if no property is filtered out */
    #[DataProvider('provideClassWithoutGroups')]
    public function testClassWithoutGroups(mixed $groups, mixed $ignoredGroups, array|null $expectedFields): void
    {
        $fields = (new GroupsPropertyFilter())->filter(
            $this->metadata(NoGroupsFixture::class),
            [
                GroupsExtension::GROUPS => $groups,
                GroupsExtension::IGNORED_GROUPS => $ignoredGroups,
            ],
        );

        self::assertSame($expectedFields, $fields === null ? null : array_keys($fields));
    }

    public function testFieldsPointToTheirProperties(): void
    {
        $metadata = $this->metadata(UserFixture::class);

        self::assertSame(
            [
                'id' => $metadata->properties['id'],
                'name' => $metadata->properties['name'],
                'address' => $metadata->properties['address'],
            ],
            (new GroupsPropertyFilter())->filter($metadata, [GroupsExtension::GROUPS => 'public']),
        );
    }

    public function testSelectionIsReused(): void
    {
        $filter = new GroupsPropertyFilter();
        $metadata = $this->metadata(UserFixture::class);

        $first = $filter->filter($metadata, [GroupsExtension::GROUPS => 'public']);

        // a changed metadata only shows for selections which are not cached yet
        $email = $metadata->properties['email'];
        $email->extras[Groups::class] = ['public'];

        $second = $filter->filter($metadata, [GroupsExtension::GROUPS => ['public']]);
        $third = $filter->filter(
            $metadata,
            [GroupsExtension::GROUPS => 'public', GroupsExtension::IGNORED_GROUPS => 'admin'],
        );

        self::assertSame(['id', 'name', 'address'], array_keys($first ?? []));
        self::assertSame($first, $second);
        self::assertSame(['name', 'email'], array_keys($third ?? []));
    }

    public function testSelectionIsCachedPerGroups(): void
    {
        $filter = new GroupsPropertyFilter();
        $metadata = $this->metadata(UserFixture::class);

        $selections = [
            [[GroupsExtension::GROUPS => [42]], null],
            [[GroupsExtension::GROUPS => [42, 'public']], ['id', 'name', 'address']],
            [[GroupsExtension::GROUPS => 'admin'], ['id', 'email', 'address']],
            [[GroupsExtension::GROUPS => ['public', 'admin']], ['id', 'name', 'email', 'address']],
            [[GroupsExtension::IGNORED_GROUPS => 'admin'], ['name', 'internal']],
        ];

        foreach ($selections as [$context, $expectedFields]) {
            $fields = $filter->filter($metadata, $context);

            self::assertSame($expectedFields, $fields === null ? null : array_keys($fields));
        }
    }

    public function testSelectionIsCachedPerClass(): void
    {
        $filter = new GroupsPropertyFilter();

        $user = $filter->filter($this->metadata(UserFixture::class), [GroupsExtension::GROUPS => 'public']);
        $noGroups = $filter->filter($this->metadata(NoGroupsFixture::class), [GroupsExtension::GROUPS => 'public']);

        self::assertSame(['id', 'name', 'address'], array_keys($user ?? []));
        self::assertSame([], $noGroups);
    }

    public function testEmptyStringGroupIsNotTreatedAsMissing(): void
    {
        $fields = (new GroupsPropertyFilter())->filter(
            $this->metadata(UserFixture::class),
            [GroupsExtension::GROUPS => ''],
        );

        self::assertSame([], $fields);
    }

    /**
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = (new AttributeMetadataFactory())->metadata($class);
        (new GroupsMetadataEnricher())->enrich($metadata);

        return $metadata;
    }
}
