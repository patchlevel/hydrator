# Hydrator

The hydrator converts objects into plain arrays (`extract`) and arrays back into
objects (`hydrate`). The default implementation is the `StackHydrator`, which
runs both operations through a stack of [middlewares](extensions.md) and
resolves [normalizers](normalizer.md) from metadata.

## Create the hydrator

The recommended way is the `StackHydratorBuilder` with the `CoreExtension`.
The `CoreExtension` registers the `TransformMiddleware`, which does the actual
property mapping, and the `BuiltInGuesser`, which picks normalizers for enums,
date types and nested objects.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->build();
```

If you don't need any extensions, you can also instantiate the `StackHydrator`
directly, it defaults to the same middleware and guesser:

```php
use Patchlevel\Hydrator\StackHydrator;

$hydrator = new StackHydrator();
```
:::tip
Use the builder as soon as you want [extensions](extensions.md), custom
[guessers](guesser.md), [lazy objects by default](lazy.md) or a
[metadata cache](caching.md).
:::

## Extract data

To convert objects into serializable arrays, use the `extract` method.

```php
use DateTimeImmutable;

$event = new ProfileCreated(
    1,
    'patchlevel',
    Role::Admin,
    [new Skill('php', 10), new Skill('event-sourcing', 10)],
    new DateTimeImmutable('2023-10-01 12:00:00'),
);

$data = $hydrator->extract($event);
```

The result is an array of scalars and nested arrays that can be passed straight
to `json_encode`:

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

## Hydrate objects

The reverse direction is the `hydrate` method. You specify the class that should
be created and the data that should be written into it.

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
The constructor is **not** called! The object is created without invoking the
constructor and the properties are written directly. Validation logic in the
constructor does not run during hydration.
:::

If a field is missing in the data and the property is a promoted constructor
parameter with a default value, the default value is used.

## Object to populate

If you want to hydrate an object that already exists, you can pass the object
to populate via the context. This is useful if you want to update an existing object.

```php
use Patchlevel\Hydrator\Hydrator;

$profile = new Profile();

$profile = $hydrator->hydrate(
    Profile::class,
    ['name' => 'patchlevel'],
    [Hydrator::OBJECT_TO_POPULATE => $profile],
);
```

## Rename fields

By default, the property name is used to name the field in the extracted
result. This can be customized with the `NormalizedName` attribute.

```php
use Patchlevel\Hydrator\Attribute\NormalizedName;

final class Profile
{
    #[NormalizedName('profile_name')]
    public string $name;
}
```

The extracted result then looks like this:

```php
[
    'profile_name' => 'patchlevel',
]
```
:::tip
You can rename a property without a backwards compatibility break in your stored
data by keeping the old serialized name with `NormalizedName`.
:::

## Ignore properties

Sometimes it is necessary to exclude properties. You can do that with the
`Ignore` attribute. The property is ignored both when extracting and when hydrating.

```php
use Patchlevel\Hydrator\Attribute\Ignore;

final readonly class ProfileCreated
{
    public function __construct(
        public string $id,
        public string $name,
        #[Ignore]
        public string $internalState,
    ) {
    }
}
```
:::warning
An ignored property is never written during hydration. Make sure it has a default
value or is set by a [lifecycle hook](lifecycle-hooks.md), otherwise it stays
uninitialized.
:::

## Error handling

Everything the library throws implements the `HydratorException` interface, so a
single catch block is enough at the boundary.

```php
use Patchlevel\Hydrator\HydratorException;

try {
    $event = $hydrator->hydrate(ProfileCreated::class, $data);
} catch (HydratorException $e) {
    // invalid data, unsupported class, type mismatch, ...
}
```
:::note
The most common exceptions are `ClassNotSupported` if the class does not exist,
`DenormalizationFailure` if a normalizer rejects a value and `TypeMismatch` if a
value does not fit the property type.
:::

## Learn more

* [How normalizers convert complex types](normalizer.md)
* [How to hydrate objects lazily](lazy.md)
* [How to hook into the hydration process](extensions.md)
