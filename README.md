[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fhydrator%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/hydrator/1.0.x)
[![Type Coverage](https://shepherd.dev/github/patchlevel/hydrator/coverage.svg)](https://shepherd.dev/github/patchlevel/hydrator)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/hydrator/v)](//packagist.org/packages/patchlevel/hydrator)
[![License](https://poser.pugx.org/patchlevel/hydrator/license)](//packagist.org/packages/patchlevel/hydrator)

# Hydrator

With this library you can hydrate objects from array into objects and back again.
It has now been outsourced by the [event-sourcing](https://github.com/patchlevel/event-sourcing) library as a separate library.

## Installation

```bash
composer require patchlevel/hydrator
```

## Usage

To use the hydrator you just have to create an instance of it.

```php
use Patchlevel\Hydrator\MetadataHydrator;

$hydrator = new MetadataHydrator();
```

After that you can hydrate any classes or objects. Also `final`, `readonly` classes with `property promotion`.

```php
final readonly class ProfileCreated 
{
    public function __construct(
        public string $id,
        public string $name
    ) {
    }
}
```

### Extract Data

To convert objects into serializable arrays, you can use the `extract` method:

```php
$event = new ProfileCreated('1', 'patchlevel');

$data = $hydrator->extract($event);
```

```php
[
  'id' => '1',
  'name' => 'patchlevel'
]
```

### Hydrate Object

```php
$event = $hydrator->hydrate(
    ProfileCreated::class,
    [
        'id' => '1',
        'name' => 'patchlevel'
    ]
);

$oldEvent == $event // true
```

### Normalizer

Sometimes you also need to extract or hydrate more complex objects.
To do that you can use normalizer.
For some the standard cases we already offer built-in normalizers.

#### Array

If you have a list of objects that you want to normalize, then you must normalize each object individually.
That's what the `ArrayNormalizer` does for you.
In order to use the `ArrayNormaliser`, you still have to specify which normaliser should be applied to the individual
objects. Internally, it basically does an `array_map` and then runs the specified normalizer on each element.

```php
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    #[ArrayNormalizer(new DateTimeImmutableNormalizer())]
    public array $dates;
}
```

> [!NOTE]
> The keys from the arrays are taken over here.

#### DateTimeImmutable

With the `DateTimeImmutable` Normalizer, as the name suggests,
you can convert DateTimeImmutable objects to a String and back again.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    #[DateTimeImmutableNormalizer]
    public DateTimeImmutable $date;
}
```

You can also define the format. Either describe it yourself as a string or use one of the existing constants.
The default is `DateTimeImmutable::ATOM`.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    #[DateTimeImmutableNormalizer(format: DateTimeImmutable::RFC3339_EXTENDED)]
    public DateTimeImmutable $date;
}
```

> [!NOTE]
> You can read about how the format is structured in the [php docs](https://www.php.net/manual/de/datetime.format.php).

#### DateTime

The `DateTime` Normalizer works exactly like the DateTimeNormalizer. Only for DateTime objects.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;

final class DTO 
{
    #[DateTimeNormalizer]
    public DateTime $date;
}
```

You can also specify the format here. The default is `DateTime::ATOM`.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;

final class DTO 
{
    #[DateTimeNormalizer(format: DateTime::RFC3339_EXTENDED)]
    public DateTime $date;
}
```

> [!NOTE]
> You can read about how the format is structured in the [php docs](https://www.php.net/manual/de/datetime.format.php).

#### DateTimeZone

To normalize a `DateTimeZone` one can use the `DateTimeZoneNormalizer`.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeZoneNormalizer;

final class DTO {
    #[DateTimeZoneNormalizer]
    public DateTimeZone $timeZone;
}
```

#### Enum

Backed enums can also be normalized.
For this, the enum FQCN must also be pass so that the `EnumNormalizer` knows which enum it is.

```php
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;

final class DTO {
    #[EnumNormalizer(Status::class)]
    public Status $status;
}
```

### Custom Normalizer

Since we only offer normalizers for PHP native things,
you have to write your own normalizers for your own structures, such as value objects.

In our example we have built a value object that should hold a name.

```php
final class Name
{
    private string $value;
    
    public function __construct(string $value) 
    {
        if (strlen($value) < 3) {
            throw new NameIsToShortException($value);
        }
        
        $this->value = $value;
    }
    
    public function toString(): string 
    {
        return $this->value;
    }
}
```

For this we now need a custom normalizer.
This normalizer must implement the `Normalizer` interface.
You also need to implement a `normalize` and `denormalize` method.
Finally, you have to allow the normalizer to be used as an attribute.

```php
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;

#[Attribute(Attribute::TARGET_PROPERTY)]
class NameNormalizer implements Normalizer
{
    public function normalize(mixed $value): string
    {
        if (!$value instanceof Name) {
            throw InvalidArgument::withWrongType(Name::class, $value);
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): ?Name
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

> [!WARNING]
> The important thing is that the result of Normalize is serializable!

Now we can also use the normalizer directly.

```php
final class DTO
{
    #[NameNormalizer]
    public Name $name
}
```

### Normalized Name

By default, the property name is used to name the field in the normalized result.
This can be customized with the `NormalizedName` attribute.

```php
use Patchlevel\Hydrator\Attribute\NormalizedName;

final class DTO
{
    #[NormalizedName('profile_name')]
    public string $name
}
```

The whole thing looks like this

```php
[
  'profile_name' => 'David'
]
```

> [!TIP]
> You can also rename properties to events without having a backwards compatibility break by keeping the serialized name.

### Ignore

Sometimes it is necessary to exclude properties. You can do that with the `Ignore` attribute. 
The property is ignored both when extracting and when hydrating.

```php
use Patchlevel\Hydrator\Attribute\Ignore;

readonly class ProfileCreated 
{
    public function __construct(
        public string $id,
        public string $name,
        #[Ignore]
        public string $ignoreMe,
    ) {
    }
}
```
