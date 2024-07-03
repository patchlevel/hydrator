[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fhydrator%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/hydrator/1.0.x)
[![Type Coverage](https://shepherd.dev/github/patchlevel/hydrator/coverage.svg)](https://shepherd.dev/github/patchlevel/hydrator)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/hydrator/v)](//packagist.org/packages/patchlevel/hydrator)
[![License](https://poser.pugx.org/patchlevel/hydrator/license)](//packagist.org/packages/patchlevel/hydrator)

# Hydrator

With this library you can hydrate objects from array into objects and back again 
with a focus on data processing from and into a database.
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

final class DTO
{
    #[DateTimeZoneNormalizer]
    public DateTimeZone $timeZone;
}
```

#### Enum

Backed enums can also be normalized.
For this, the enum FQCN must also be pass so that the `EnumNormalizer` knows which enum it is.

```php
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;

final class DTO
{
    #[EnumNormalizer]
    public Status $status;
}
```

#### Object

If you have a complex object that you want to normalize, you can use the `ObjectNormalizer`.
This use the hydrator internally to normalize the object.

```php
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class DTO
{
    #[ObjectNormalizer]
    public AnohterDto $anotherDto;
    
    #[ObjectNormalizer(AnohterDto::class)]
    public object $object;
}

final class AnotherDto
{
    #[EnumNormalizer]
    public Status $status;
}
```

> [!WARNING]
> Circular references are not supported and will result in an exception.

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

### Define normalizer on class level

You can also set the attribute on the value object on class level. For that the normalizer needs to allow to be set on 
class level.

```php
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class NameNormalizer implements Normalizer
{
    // ... same as before
}
```

Then set the attribute on the value object.


```php
#[NameNormalizer]
final class Name
{
    // ... same as before
}
```

After that the DTO can then look like this.

```php
final class DTO
{
    public Name $name
}
```

### Infer Normalizer

We also integrated a process where the normalizer gets inferred by type. This means you don't need to define the 
normalizer in for the properties or on class level. Right now this is only possible for Normalizer defined by our 
library. There are exceptions though, the `ObjectNormalizer` and the `ArrayNormalizer`.

These Normalizer can be inferred:

* `DateTimeImmutableNormalizer` 
* `DateTimeNormalizer` 
* `DateTimeZoneNormalizer` 
* `EnumNormalizer` 


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

### Cryptography

The library also offers the possibility to encrypt and decrypt personal data.

#### PersonalData

First of all, we have to mark the fields that contain personal data.
For our example, we use events, but you can do the same with aggregates.

```php
use Patchlevel\Hydrator\Attribute\PersonalData;

final class DTO 
{
    #[PersonalData]
    public readonly string|null $email;
}
```

If the information could not be decrypted, then a fallback value is inserted.
The default fallback value is `null`.
You can change this by setting the `fallback` parameter.
In this case `unknown` is added:

```php
use Patchlevel\Hydrator\Attribute\PersonalData;

final class DTO
{
    public function __construct(
        #[PersonalData(fallback: 'unknown')]
        public readonly string $email,
    ) {
    }
}
```

> [!DANGER]
> You have to deal with this case in your business logic such as aggregates and subscriptions.

> [!WARNING]
> You need to define a subject ID to use the personal data attribute.

#### DataSubjectId

In order for the correct key to be used, a subject ID must be defined.
Without Subject Id, no personal data can be encrypted or decrypted.

```php
use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $personId,
        #[PersonalData(fallback: 'unknown')]
        public readonly string|null $email,
    ) {
    }
}
```

> [!WARNING]
> A subject ID can not be a personal data.

#### Configure Cryptography

Here we show you how to configure the cryptography.

```php
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Metadata\Event\EventMetadataFactory;
use Patchlevel\Hydrator\MetadataHydrator;

$cipherKeyStore = new InMemoryCipherKeyStore();
$cryptographer = PersonalDataPayloadCryptographer::createWithOpenssl($cipherKeyStore);
$hydrator = new MetadataHydrator(cryptographer: $cryptographer);
```

#### Cipher Key Store

The keys must be stored somewhere. For testing purposes, we offer an in-memory implementation.

```php
use Patchlevel\Hydrator\Cryptography\Cipher\CipherKey;
use Patchlevel\Hydrator\Cryptography\Store\InMemoryCipherKeyStore;

$cipherKeyStore = new InMemoryCipherKeyStore();

/** @var CipherKey $cipherKey */
$cipherKeyStore->store('foo-id', $cipherKey);
$cipherKey = $cipherKeyStore->get('foo-id');
$cipherKeyStore->remove('foo-id');
```

Because we don't know where you want to store the keys, we don't offer any other implementations.
You should use a database or a key store for this. To do this, you have to implement the `CipherKeyStore` interface.

#### Remove personal data

To remove personal data, you need only remove the key from the store.

```php
$cipherKeyStore->remove('foo-id');
```