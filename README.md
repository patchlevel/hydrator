[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fhydrator%2F1.13.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/hydrator/1.13.x)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/hydrator/v)](//packagist.org/packages/patchlevel/hydrator)
[![License](https://poser.pugx.org/patchlevel/hydrator/license)](//packagist.org/packages/patchlevel/hydrator)

# Hydrator

This library enables seamless hydration of objects to arrays—and back again.
It’s optimized for both developer experience (DX) and performance.

The library is a core component of [patchlevel/event-sourcing](ttps://github.com/patchlevel/event-sourcing),
where it powers the storage and retrieval of thousands of objects.

Hydration is handled through normalizers, especially for complex data types.
The system can automatically determine the appropriate normalizer based on the data type and annotations.

In most cases, no manual configuration is needed.
And if customization is required, it can be done easily using attributes.

## Installation

```bash
composer require patchlevel/hydrator
```

## Usage

To use the hydrator you just have to create an instance of it.

```php
use Patchlevel\Hydrator\MetadataHydrator;

$hydrator = MetadataHydrator::create();
```

After that you can hydrate any classes or objects. Also `final`, `readonly` classes with `property promotion`.
These objects or classes can have complex structures in the form of value objects, DTOs or collections.
Or all nested together. Here's an example:

```php
final readonly class ProfileCreated 
{
    /**
     * @param list<Skill> $skills
     */
    public function __construct(
        public int $id,
        public string $name,
        public Role $role, // enum,
        public array $skills, // array of objects
        public DateTimeImmutable $createdAt,
    ) {
    }
}
```

### Extract Data

To convert objects into serializable arrays, you can use the `extract` method of the hydrator.

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
    [
      'name' => 'php',
      'level' => 10,
    ],
    [
      'name' => 'event-sourcing',
      'level' => 10,
    ],
  ],
  'createdAt' => '2023-10-01T12:00:00+00:00',
]
```

We could now convert the whole thing into JSON using `json_encode`.

### Hydrate Object

The process can also be reversed. Hydrate an array back into an object. 
To do this, we need to specify the class that should be created 
and the data that should then be written into it.

```php
$event = $hydrator->hydrate(
    ProfileCreated::class,
    [
      'id' => 1,
      'name' => 'patchlevel',
      'role' => 'admin',
      'skills' => [
        [
          'name' => 'php',
          'level' => 10,
        ],
        [
          'name' => 'event-sourcing',
          'level' => 10,
        ],
      ],
      'createdAt' => '2023-10-01T12:00:00+00:00',
    ]
);

$oldEvent == $event // true
```

> [!WARNING]
> It is important to know that the constructor is not called!

### Normalizer

For more complex structures, i.e. non-scalar data types, we use normalizers.
We have some built-in normalizers for standard structures such as objects, arrays, enums, datetime etc. 
You can find the full list below.

The library attempts to independently determine which normalizers should be used.
For this purpose, normalizers of this order are determined:

1) Does the class property have a normalizer as an attribute? Use this.
2) The data type of the property is determined.
   1) If it is an array shape, use the ArrayShapeNormalizer (recursive).
   2) If it is a collection, use the ArrayNormalizer (recursive).
   3) If it is an object, then look for a normalizer as attribute on the class or interfaces and use this.
   4) If it is an object, then guess the normalizer based on the object. Fallback to the object normalizer.

The normalizer is only determined once because it is cached in the metadata.
Below you will find the list of all normalizers and how to set them manually or explicitly.

#### Array

If you have a collection (array, iterable, list) with a data type that needs to be normalized, 
you can use the ArrayNormalizer and pass it the required normalizer.

```php
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    /**
     * @var list<DateTimeImmutable>
     */
    #[ArrayNormalizer]
    public array $dates;
    
    #[ArrayNormalizer(new DateTimeImmutableNormalizer())]
    public array $explicitDates;
}
```

> [!NOTE]
> The keys from the arrays are taken over here.

#### ArrayShape

If you have an array with a specific shape, you can use the `ArrayShapeNormalizer`.

```php
use Patchlevel\Hydrator\Normalizer\ArrayShapeNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    /**
     * @var array{
     *     date: DateTimeImmutable,
     *     otherField: string
     * }
     */
    #[ArrayShapeNormalizer]
    public array $meta;
    
    #[ArrayShapeNormalizer(['date' => new DateTimeImmutableNormalizer()])]
    public array $explicitMeta;
}
```

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

#### ObjectMap

You can also use the `ObjectMapNormalizer` if you have either inheritance or a union type.

```php
use Patchlevel\Hydrator\Normalizer\ObjectMapNormalizer;

// inheritance
#[ObjectMapNormalizer([
    ContentBlock::class => 'content',
    CodeBlock::class => 'code'
])]
interface Block
{
}

// union type
class ContentBlock implements Block {
    #[ObjectMapNormalizer([
       ContentA::class => 'content',
       ContentB::class => 'code'
    ])]
    public ContentA|ContentB $content;
}
```

> [!NOTE]
> Auto detection of the type is not possible. You have to specify the type yourself.

#### Inline

The `InlineNormalizer` allows you to define normalization and denormalization logic directly via closures.
This is useful for simple value objects or when you don't want to create a separate normalizer class.

```php
use Patchlevel\Hydrator\Normalizer\InlineNormalizer;

#[InlineNormalizer(
    normalize: static function (self $object): string {
        return $object->toString();
    },
    denormalize: static function (string $value): self {
        return new self($value);
    },
)]
final class Email
{
    public function __construct(
        private string $value
    ) {}

    public function toString(): string
    {
        return $this->value;
    }
}
```

> [!NOTE] 
> Closures in attributes can only be used since PHP 8.5, therefore this normalizer can only be used with PHP 8.5.

> [!TIP]
> If you want to handle `null` values within your closures, you can set the `passNull` option to `true`.
> By default, `null` values are not passed to the closures and are returned as `null` directly.

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
Finally, you have to allow the normalizer to be used as an attribute,
best to allow it for properties as well as classes.

```php
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
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

Now we can also use the normalizer directly.

```php
final class DTO
{
    #[NameNormalizer]
    public Name $name
}
```

### Define normalizer on class level

Instead of specifying the normalizer on each property, you can also set the normalizer on the class or on an interface.

```php
#[NameNormalizer]
final class Name
{
    // ... same as before
}
```

### Guess normalizer

It's also possible to write your own guesser that finds the correct normalizer based on the object. 
This is useful if, for example, setting the normalizer on the class or interface isn't possible.

```php
use Patchlevel\Hydrator\Guesser\Guesser;
use Symfony\Component\TypeInfo\Type\ObjectType;

class NameGuesser implements Guesser
{
    public function guess(ObjectType $object): Normalizer|null
    {
        return match($object->getClassName()) {
            case Name::class => new NameNormalizer(),
            default => null,
        };
    }
}
```

To use this Guesser, you must specify it when creating the Hydrator:

```php
use Patchlevel\Hydrator\MetadataHydrator;

$hydrator = MetadataHydrator::create([new NameGuesser()]);
```

> [!NOTE]
> The guessers are queried in order, and the first match is returned. Finally, our built-in guesser is executed.

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

### Lazy

Since PHP 8.4, it's been possible to lazy-hydrate objects. 
That is, the actual hydration process occurs when the object is accessed.
You can define for each class whether you want it to be lazy by using the `Lazy` attribute.

```php
use Patchlevel\Hydrator\Attribute\Lazy;

#[Lazy]
readonly class ProfileCreated 
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
```

> [!NOTE]
> If you are using a PHP version older than 8.4, the attribute will be ignored.

### Hooks

Sometimes you need to do something before extract or after hydrate process.
For this we have the `PreExtract` and `PostHydrate` attributes.

```php
use Patchlevel\Hydrator\Attribute\PostHydrate;
use Patchlevel\Hydrator\Attribute\PreExtract;

readonly class Dto 
{    
    #[PostHydrate]
    private function postHydrate(): void
    {
        // do something
    }
    
    #[PreExtract]
    private function preExtract(): void
    {
        // do something
    }
}
```

### Events

Another way to intervene in the extract and hydrate process is through events. 
There are two events: `PostExtract` and `PreHydrate`.
For this functionality we use the [symfony/event-dispatcher](https://symfony.com/doc/current/components/event_dispatcher.html).

```php
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Metadata\Event\EventMetadataFactory;
use Patchlevel\Hydrator\MetadataHydrator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Patchlevel\Hydrator\Event\PostExtract;
use Patchlevel\Hydrator\Event\PreHydrate;

$eventDispatcher = new EventDispatcher();

$eventDispatcher->addListener(
    PostExtract::class,
    static function (PostExtract $event): void {
        // do something
    }
);

$eventDispatcher->addListener(
    PreHydrate::class,
    static function (PreHydrate $event): void {
        // do something
    }
);

$hydrator = new MetadataHydrator(eventDispatcher: $eventDispatcher);
```

### Cryptography

The library also offers the possibility to encrypt and decrypt personal data.
For this purpose, a key is created for each subject ID, which is used to encrypt the personal data.

#### DataSubjectId

First we need to define what the subject id is.

```php
use Patchlevel\Hydrator\Attribute\DataSubjectId;

final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $profileId,
    ) {
    }
}
```

> [!WARNING]
> The `DataSubjectId` must be a string. You can use a normalizer to convert it to a string.
> The Subject ID cannot be personal data.

#### PersonalData

Next, we need to specify which fields we want to encrypt.

```php
use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

final class DTO 
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $profileId,
        #[PersonalData]
        public readonly string|null $email,
    ) {
    }
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
        #[DataSubjectId]
        public readonly string $profileId,
        #[PersonalData(fallback: 'unknown')]
        public readonly string $name,
    ) {
    }
}
```

You can also use a callable as a fallback.

```php
use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

final class ProfileCreated
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $profileId,
        #[PersonalData(fallback: 'deleted profile')]
        public readonly string $name,
        #[PersonalData(fallbackCallable: [self::class, 'anonymizedEmail'])]
        public readonly string $email,
    ) {
    }
    
    public static function anonymizedEmail(string $subjectId): string
    {
        return sprintf('%s@anno.com', $subjectId);
    }
}
```

> [!TIP]
> Cryptography is very expensive in terms of performance, 
> you can combine it with lazy to improve performance and only decrypt when you actually access the object.

#### Configure Cryptography

Here we show you how to configure the cryptography.

```php
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Metadata\Event\EventMetadataFactory;
use Patchlevel\Hydrator\MetadataHydrator;

$cipherKeyStore = new InMemoryCipherKeyStore();
$cryptographer = PersonalDataPayloadCryptographer::createWithDefaultSettings($cipherKeyStore);
$hydrator = new MetadataHydrator(cryptographer: $cryptographer);
```

> [!WARNING]
> We recommend to use the `useEncryptedFieldName` option to recognize encrypted fields.
> This allows data to be encrypted later without big troubles.

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


## Contributing

We are open to contributions as long as they are in line with
our [BC-Policy](https://event-sourcing.patchlevel.io/latest/our-backward-compatibility-promise/).

Also note that the `composer.lock` is always generated with the newest supported PHP version as this is the version our tools run in the CI.