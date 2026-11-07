<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Groups;

use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;
use Patchlevel\Hydrator\Extension\Groups\GroupsMetadataEnricher;
use Patchlevel\Hydrator\Extension\Groups\GroupsMiddleware;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\AddressFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\NoGroupsFixture;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\RecordingMiddleware;
use Patchlevel\Hydrator\Tests\Unit\Extension\Groups\Fixture\UserFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_keys;

#[CoversClass(GroupsMiddleware::class)]
final class GroupsMiddlewareTest extends TestCase
{
    /** @return iterable<string, array{mixed, list<string>}> */
    public static function provideGroups(): iterable
    {
        yield 'no groups' => [null, ['id', 'name', 'email', 'address', 'internal']];
        yield 'empty list' => [[], ['id', 'name', 'email', 'address', 'internal']];
        yield 'invalid value' => [42, ['id', 'name', 'email', 'address', 'internal']];
        yield 'only invalid entries' => [[42, null], ['id', 'name', 'email', 'address', 'internal']];
        yield 'string' => ['admin', ['id', 'email', 'address']];
        yield 'list' => [['public'], ['id', 'name', 'address']];
        yield 'invalid entries are ignored' => [['public', 42], ['id', 'name', 'address']];
        yield 'unknown group' => [['unknown'], []];
        yield 'all' => [['unknown', GroupsExtension::ALL], ['id', 'name', 'email', 'address', 'internal']];
    }

    /** @param list<string> $expectedProperties */
    #[DataProvider('provideGroups')]
    public function testExtract(mixed $groups, array $expectedProperties): void
    {
        $inner = new RecordingMiddleware();

        (new GroupsMiddleware())->extract(
            $this->metadata(),
            $this->user(),
            [GroupsExtension::GROUPS => $groups],
            new Stack([$inner]),
        );

        self::assertNotNull($inner->metadata);
        self::assertSame($expectedProperties, array_keys($inner->metadata->properties));
    }

    /** @param list<string> $expectedProperties */
    #[DataProvider('provideGroups')]
    public function testHydrate(mixed $groups, array $expectedProperties): void
    {
        $inner = new RecordingMiddleware();
        $metadata = $this->metadata();

        (new GroupsMiddleware())->hydrate(
            $metadata,
            [
                'id' => '1',
                'name' => 'John',
                'email' => 'john@example.com',
                'address' => [],
                'internal' => 'secret',
                'unknown' => 'value',
            ],
            [GroupsExtension::GROUPS => $groups],
            new Stack([$inner]),
        );

        self::assertSame($metadata, $inner->metadata);

        // fields without a property are not touched
        self::assertSame([...$expectedProperties, 'unknown'], array_keys($inner->data));
    }

    /** @return iterable<string, array{mixed, mixed, list<string>}> */
    public static function provideIgnoredGroups(): iterable
    {
        yield 'string' => [null, 'admin', ['name', 'internal']];
        yield 'list' => [null, ['admin', 'public'], ['internal']];
        yield 'unknown group' => [null, ['unknown'], ['id', 'name', 'email', 'address', 'internal']];
        yield 'empty list' => [null, [], ['id', 'name', 'email', 'address', 'internal']];
        yield 'with groups' => ['public', 'admin', ['name']];
        yield 'with all' => [GroupsExtension::ALL, 'admin', ['name', 'internal']];
    }

    /** @param list<string> $expectedProperties */
    #[DataProvider('provideIgnoredGroups')]
    public function testExtractWithIgnoredGroups(mixed $groups, mixed $ignoredGroups, array $expectedProperties): void
    {
        $inner = new RecordingMiddleware();

        (new GroupsMiddleware())->extract(
            $this->metadata(),
            $this->user(),
            [
                GroupsExtension::GROUPS => $groups,
                GroupsExtension::IGNORED_GROUPS => $ignoredGroups,
            ],
            new Stack([$inner]),
        );

        self::assertNotNull($inner->metadata);
        self::assertSame($expectedProperties, array_keys($inner->metadata->properties));
    }

    /** @param list<string> $expectedProperties */
    #[DataProvider('provideIgnoredGroups')]
    public function testHydrateWithIgnoredGroups(mixed $groups, mixed $ignoredGroups, array $expectedProperties): void
    {
        $inner = new RecordingMiddleware();

        (new GroupsMiddleware())->hydrate(
            $this->metadata(),
            [
                'id' => '1',
                'name' => 'John',
                'email' => 'john@example.com',
                'address' => [],
                'internal' => 'secret',
            ],
            [
                GroupsExtension::GROUPS => $groups,
                GroupsExtension::IGNORED_GROUPS => $ignoredGroups,
            ],
            new Stack([$inner]),
        );

        self::assertSame($expectedProperties, array_keys($inner->data));
    }

    /** @return iterable<string, array{mixed, mixed, list<string>}> */
    public static function provideClassWithoutGroups(): iterable
    {
        yield 'groups' => ['public', null, []];
        yield 'all' => [GroupsExtension::ALL, null, ['name', 'age']];
        yield 'ignored groups' => [null, 'admin', ['name', 'age']];
        yield 'groups and ignored groups' => ['public', 'admin', []];
    }

    /** @param list<string> $expectedProperties */
    #[DataProvider('provideClassWithoutGroups')]
    public function testExtractClassWithoutGroups(mixed $groups, mixed $ignoredGroups, array $expectedProperties): void
    {
        $inner = new RecordingMiddleware();

        (new GroupsMiddleware())->extract(
            $this->noGroupsMetadata(),
            new NoGroupsFixture(),
            [
                GroupsExtension::GROUPS => $groups,
                GroupsExtension::IGNORED_GROUPS => $ignoredGroups,
            ],
            new Stack([$inner]),
        );

        self::assertNotNull($inner->metadata);
        self::assertSame($expectedProperties, array_keys($inner->metadata->properties));
    }

    /** @param list<string> $expectedProperties */
    #[DataProvider('provideClassWithoutGroups')]
    public function testHydrateClassWithoutGroups(mixed $groups, mixed $ignoredGroups, array $expectedProperties): void
    {
        $inner = new RecordingMiddleware();
        $metadata = $this->noGroupsMetadata();

        (new GroupsMiddleware())->hydrate(
            $metadata,
            ['name' => 'John', 'age' => 42],
            [
                GroupsExtension::GROUPS => $groups,
                GroupsExtension::IGNORED_GROUPS => $ignoredGroups,
            ],
            new Stack([$inner]),
        );

        self::assertSame($metadata, $inner->metadata);
        self::assertSame($expectedProperties, array_keys($inner->data));
    }

    public function testSelectionIsReused(): void
    {
        $middleware = new GroupsMiddleware();
        $metadata = $this->metadata();

        $first = new RecordingMiddleware();
        $middleware->extract($metadata, $this->user(), [GroupsExtension::GROUPS => 'public'], new Stack([$first]));

        $second = new RecordingMiddleware();
        $middleware->extract($metadata, $this->user(), [GroupsExtension::GROUPS => ['public']], new Stack([$second]));

        $third = new RecordingMiddleware();
        $middleware->extract(
            $metadata,
            $this->user(),
            [GroupsExtension::GROUPS => 'public', GroupsExtension::IGNORED_GROUPS => 'admin'],
            new Stack([$third]),
        );

        self::assertNotSame($metadata, $first->metadata);
        self::assertSame($first->metadata, $second->metadata);
        self::assertNotSame($first->metadata, $third->metadata);
    }

    public function testEmptyStringGroupIsNotTreatedAsMissing(): void
    {
        $inner = new RecordingMiddleware();

        (new GroupsMiddleware())->extract(
            $this->metadata(),
            $this->user(),
            [GroupsExtension::GROUPS => ''],
            new Stack([$inner]),
        );

        self::assertNotNull($inner->metadata);
        self::assertSame([], $inner->metadata->properties);
    }

    /** @return ClassMetadata<UserFixture> */
    private function metadata(): ClassMetadata
    {
        $metadata = (new AttributeMetadataFactory())->metadata(UserFixture::class);
        (new GroupsMetadataEnricher())->enrich($metadata);

        return $metadata;
    }

    /** @return ClassMetadata<NoGroupsFixture> */
    private function noGroupsMetadata(): ClassMetadata
    {
        $metadata = (new AttributeMetadataFactory())->metadata(NoGroupsFixture::class);
        (new GroupsMetadataEnricher())->enrich($metadata);

        return $metadata;
    }

    private function user(): UserFixture
    {
        return new UserFixture('1', 'John', 'john@example.com', new AddressFixture('Berlin', 'Main Street'));
    }
}
