# Normalizer

For complex structures, i.e. non-scalar data types, the hydrator uses normalizers.
A normalizer converts a value into a serializable representation (`normalize`)
and back into the original type (`denormalize`). The library ships normalizers
for all PHP native structures such as enums, date types, collections and objects,
and determines on its own which one to use.

## How normalizers are resolved

For every property, the normalizer is determined in this order:

1. Does the property have a normalizer as an attribute? Use this.
2. Otherwise, the type of the property is determined:
    1. If it is an array shape, the `ArrayShapeNormalizer` is used (recursive).
    2. If it is a collection, the `ArrayNormalizer` is used (recursive).
    3. If it is an object, a normalizer attribute is searched on the class, its parents and interfaces.
    4. If none is found, the [guessers](guesser.md) are asked. The built-in guesser handles enums and date types and falls back to the `ObjectNormalizer`.

The normalizer is only determined once per class because it is cached in the
[metadata](caching.md).

## Array

If you have a collection (array, iterable, list) the element type is read from
the docblock and the matching normalizer is applied to every element automatically.

```php
final readonly class ProfileCreated
{
    /** @param list<Skill> $skills */
    public function __construct(
        public array $skills,
    ) {
    }
}
```

You can also set the `ArrayNormalizer` explicitly and pass it the normalizer
for the elements:

```php
use DateTimeImmutable;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class Profile
{
    /** @var list<DateTimeImmutable> */
    #[ArrayNormalizer(new DateTimeImmutableNormalizer())]
    public array $loginDates;
}
```
:::note
The keys of the array are kept.
:::

## ArrayShape

If you have an array with a specific shape, the `ArrayShapeNormalizer` is used.
It is inferred automatically from an `array{...}` docblock, or you can configure
it explicitly with a map of field name to normalizer.

```php
use DateTimeImmutable;
use Patchlevel\Hydrator\Normalizer\ArrayShapeNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class Profile
{
    /**
     * @var array{
     *     createdAt: DateTimeImmutable,
     *     source: string
     * }
     */
    public array $meta;

    #[ArrayShapeNormalizer(['createdAt' => new DateTimeImmutableNormalizer()])]
    public array $explicitMeta;
}
```

## DateTimeImmutable

With the `DateTimeImmutableNormalizer` you can convert `DateTimeImmutable`
objects to a string and back again. It is applied automatically to
`DateTimeImmutable` properties.

```php
use DateTimeImmutable;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class Profile
{
    #[DateTimeImmutableNormalizer]
    public DateTimeImmutable $createdAt;
}
```

You can also define the format. Either describe it yourself as a string or use
one of the existing constants. The default is `DateTimeImmutable::ATOM`.

```php
use DateTimeImmutable;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class Profile
{
    #[DateTimeImmutableNormalizer(format: DateTimeImmutable::RFC3339_EXTENDED)]
    public DateTimeImmutable $createdAt;
}
```
:::note
You can read about how the format is structured in the [php docs](https://www.php.net/manual/en/datetime.format.php).
:::

## DateTime

The `DateTimeNormalizer` works exactly like the `DateTimeImmutableNormalizer`,
only for `DateTime` objects. The default format is `DateTime::ATOM`.

```php
use DateTime;
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;

final class Profile
{
    #[DateTimeNormalizer(format: DateTime::RFC3339_EXTENDED)]
    public DateTime $lastSeen;
}
```

## DateTimeZone

To normalize a `DateTimeZone`, the `DateTimeZoneNormalizer` is used.

```php
use DateTimeZone;
use Patchlevel\Hydrator\Normalizer\DateTimeZoneNormalizer;

final class Profile
{
    #[DateTimeZoneNormalizer]
    public DateTimeZone $timeZone;
}
```

## DateInterval

A `DateInterval` is converted to its ISO 8601 duration string with the
`DateIntervalNormalizer`. The format can be customized.

```php
use DateInterval;
use Patchlevel\Hydrator\Normalizer\DateIntervalNormalizer;

final class Subscription
{
    #[DateIntervalNormalizer]
    public DateInterval $renewEvery;
}
```

## Enum

Backed enums are converted to their backing value. The enum class is inferred
from the property type, but can also be passed explicitly.

```php
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;

final class Profile
{
    #[EnumNormalizer]
    public Role $role;

    #[EnumNormalizer(Role::class)]
    public mixed $explicitRole;
}
```

## Object

If you have a complex object that you want to normalize, the `ObjectNormalizer`
is used. It runs the [hydrator](hydrator.md) recursively on the object. It is
the automatic fallback for object properties, so you only need the attribute
when the class cannot be inferred from the type.

```php
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class Profile
{
    #[ObjectNormalizer]
    public Address $address;

    #[ObjectNormalizer(Address::class)]
    public object $untypedAddress;
}
```
:::warning
Circular references are not supported and result in a `CircularReference` exception.
:::

## ObjectMap

Use the `ObjectMapNormalizer` if you have either inheritance or a union type,
where the concrete class can not be derived from the property type alone.
The map assigns a stable type name to every class, which is stored in the data
under the `_type` field (configurable via `typeFieldName`).

```php
use Patchlevel\Hydrator\Normalizer\ObjectMapNormalizer;

#[ObjectMapNormalizer([
    ContentBlock::class => 'content',
    CodeBlock::class => 'code',
])]
interface Block
{
}

final class Page
{
    #[ObjectMapNormalizer(
        [TextSection::class => 'text', ImageSection::class => 'image'],
        typeFieldName: 'kind',
    )]
    public TextSection|ImageSection $section;
}
```
:::note
Auto detection of the concrete type is not possible here. You have to specify the
map yourself.
:::

## Inline

The `InlineNormalizer` allows you to define normalization and denormalization
logic directly via closures. This is useful for simple value objects when you
don't want to create a separate normalizer class.

```php
use Patchlevel\Hydrator\Normalizer\InlineNormalizer;

#[InlineNormalizer(
    normalize: static fn (self $email): string => $email->toString(),
    denormalize: static fn (string $value): self => new self($value),
)]
final class Email
{
    public function __construct(
        private string $value,
    ) {
    }

    public function toString(): string
    {
        return $this->value;
    }
}
```
:::note
Closures in attributes are only possible since PHP 8.5, therefore this normalizer
can only be used as an attribute with PHP 8.5.
:::

:::tip
If you want to handle `null` values within your closures, you can set the
`passNull` option to `true`. By default, `null` values are not passed to the
closures and are returned as `null` directly.
:::

## Custom Normalizer

The library only offers normalizers for PHP native things, so for your own
structures, such as value objects, you write a custom normalizer. It must
implement the `Normalizer` interface. To use it as an attribute, allow it for
properties as well as classes.

In this example we have a value object that holds a validated name:

```php
final class Name
{
    private string $value;

    public function __construct(string $value)
    {
        if (strlen($value) < 3) {
            throw new NameIsTooShort($value);
        }

        $this->value = $value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
```

The matching normalizer converts it to a string and back:

```php
use Attribute;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class NameNormalizer implements Normalizer
{
    public function normalize(mixed $value, array $context): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Name) {
            throw InvalidArgument::withWrongType(Name::class, $value);
        }

        return $value->toString();
    }

    public function denormalize(mixed $value, array $context): Name|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        return new Name($value);
    }
}
```

Now you can use the normalizer directly on a property:

```php
final class Profile
{
    #[NameNormalizer]
    public Name $name;
}
```

## Define a normalizer on class level

Instead of specifying the normalizer on each property, you can also set the
normalizer on the class or on an interface. Every property typed with that
class then uses it automatically.

```php
#[NameNormalizer]
final class Name
{
    // ... same as before
}
```
:::tip
If you can't put an attribute on the class, for example for third-party classes,
write a [guesser](guesser.md) instead.
:::

## Learn more

* [How to guess normalizers for third-party classes](guesser.md)
* [How to rename or ignore fields](hydrator.md)
* [How to use the hydrator](hydrator.md)
