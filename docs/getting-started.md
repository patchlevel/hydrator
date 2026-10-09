# Getting Started

In this guide you build a small profile domain and use the hydrator to convert
its objects into plain arrays and back. Everything you see here works without
any configuration, the hydrator figures out the normalizers on its own.

## Define the classes

We start with a backed enum for the role, a `Skill` value object and a
`ProfileCreated` event that combines them. All classes are `final`, `readonly`
and use constructor property promotion, the hydrator supports all of it.

```php
enum Role: string
{
    case Admin = 'admin';
    case Member = 'member';
}

final readonly class Skill
{
    public function __construct(
        public string $name,
        public int $level,
    ) {
    }
}

final readonly class ProfileCreated
{
    /** @param list<Skill> $skills */
    public function __construct(
        public int $id,
        public string $name,
        public Role $role,
        public array $skills,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
```
:::note
The `@param list<Skill>` docblock is what tells the hydrator the element type of
the collection. How types are resolved into normalizers is explained on the
[normalizer](normalizer.md) page.
:::

## Create the hydrator

The recommended way to create a hydrator is the `StackHydratorBuilder` together
with the `CoreExtension`, which registers the default middleware and the
built-in normalizer guesser.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->build();
```
:::note
The builder is also the place to add [extensions](extensions.md), custom
[guessers](guesser.md) and a [metadata cache](caching.md).
:::

## Extract data

To convert an object into a serializable array, use the `extract` method.

```php
$event = new ProfileCreated(
    1,
    'patchlevel',
    Role::Admin,
    [new Skill('php', 10), new Skill('event-sourcing', 10)],
    new DateTimeImmutable('2023-10-01 12:00:00'),
);

$data = $hydrator->extract($event);
```

The result looks like this:

```php
[
    'id' => 1,
    'name' => 'patchlevel',
    'role' => 'admin',
    'skills' => [
        ['name' => 'php', 'level' => 10],
        ['name' => 'event-sourcing', 'level' => 10],
    ],
    'createdAt' => '2023-10-01T12:00:00+00:00',
]
```

You can now turn this array into JSON with `json_encode` and store it anywhere.

## Hydrate the object back

The process can be reversed with the `hydrate` method. You pass the class that
should be created and the data that should be written into it.

```php
$event = $hydrator->hydrate(
    ProfileCreated::class,
    [
        'id' => 1,
        'name' => 'patchlevel',
        'role' => 'admin',
        'skills' => [
            ['name' => 'php', 'level' => 10],
            ['name' => 'event-sourcing', 'level' => 10],
        ],
        'createdAt' => '2023-10-01T12:00:00+00:00',
    ],
);
```
:::warning
The constructor is **not** called during hydration. The properties are written
directly, so constructor validation does not run. You can find more details on the
[hydrator](hydrator.md) page.
:::

## Result

You can now round-trip arbitrarily nested objects: enums, date types,
collections and nested value objects are handled automatically. When the
automatic resolution is not enough, you attach a [normalizer](normalizer.md)
to the property or class, and that is usually all the configuration you ever need.

## Learn more

* [How to use the hydrator in depth](hydrator.md)
* [How normalizers are resolved and which ones exist](normalizer.md)
* [How to rename or ignore fields](hydrator.md)
* [How to hydrate objects lazily](lazy.md)
