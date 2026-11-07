# Upgrade 2.0

## Hydrator

The hydrator was rebuilt on top of a middleware stack. The old
`MetadataHydrator` and its event dispatcher based extension points are gone, the
new entry points are the `StackHydrator` and the `StackHydratorBuilder`.

### MetadataHydrator

`MetadataHydrator` has been removed in favor of `StackHydrator`. The static
`create()` factory is gone, build the hydrator with the `StackHydratorBuilder`
and register the `CoreExtension`, which sets up the property mapping and the
built-in guesser.

before:
```php
use Patchlevel\Hydrator\MetadataHydrator;

$hydrator = MetadataHydrator::create();
```
after:
```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->build();
```

If you do not need any extensions, you can also instantiate the `StackHydrator`
directly, it defaults to the same middleware and guesser.

before:
```php
use Patchlevel\Hydrator\MetadataHydrator;

$hydrator = new MetadataHydrator();
```
after:
```php
use Patchlevel\Hydrator\StackHydrator;

$hydrator = new StackHydrator();
```

### Custom guessers

The guessers are no longer passed to a static factory, register them on the
builder instead.

before:
```php
use Patchlevel\Hydrator\MetadataHydrator;

$hydrator = MetadataHydrator::create([new NameGuesser()]);
```
after:
```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->addGuesser(new NameGuesser())
    ->build();
```

### Default lazy objects

The `defaultLazy` constructor flag moved to `enableDefaultLazy()` on the builder.

before:
```php
use Patchlevel\Hydrator\MetadataHydrator;

$hydrator = new MetadataHydrator(defaultLazy: true);
```
after:
```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->enableDefaultLazy()
    ->build();
```

### HydratorWithContext

The `HydratorWithContext` interface has been removed, its methods were merged
into the `Hydrator` interface. `hydrate` and `extract` now always take a
`$context` array, and the `OBJECT_TO_POPULATE` constant lives on `Hydrator`.
Type hint against `Hydrator` and read the constant from there.

before:
```php
use Patchlevel\Hydrator\HydratorWithContext;

function hydrate(HydratorWithContext $hydrator, Profile $profile): Profile
{
    return $hydrator->hydrate(
        Profile::class,
        ['name' => 'patchlevel'],
        [HydratorWithContext::OBJECT_TO_POPULATE => $profile],
    );
}
```
after:
```php
use Patchlevel\Hydrator\Hydrator;

function hydrate(Hydrator $hydrator, Profile $profile): Profile
{
    return $hydrator->hydrate(
        Profile::class,
        ['name' => 'patchlevel'],
        [Hydrator::OBJECT_TO_POPULATE => $profile],
    );
}
```

## Normalizer

### Normalizer interface

The `normalize` and `denormalize` methods now receive a mandatory `array $context`
parameter. The separate `NormalizerWithContext` interface has been removed,
every normalizer gets the context. Add the parameter to all of your custom
normalizers.

before:
```php
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;

use function is_string;

final class NameNormalizer implements Normalizer
{
    public function normalize(mixed $value): string|null
    {
        if (!$value instanceof Name) {
            throw InvalidArgument::withWrongType(Name::class, $value);
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): Name|null
    {
        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        return new Name($value);
    }
}
```
after:
```php
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;

use function is_string;

final class NameNormalizer implements Normalizer
{
    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context): string|null
    {
        if (!$value instanceof Name) {
            throw InvalidArgument::withWrongType(Name::class, $value);
        }

        return $value->toString();
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): Name|null
    {
        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        return new Name($value);
    }
}
```

### ReflectionTypeAwareNormalizer

`ReflectionTypeAwareNormalizer` has been removed in favor of
`TypeAwareNormalizer`. It no longer hands you a native `ReflectionType` but a
`Symfony\Component\TypeInfo\Type`, and the method was renamed from
`handleReflectionType()` to `handleType()`.

before:
```php
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ReflectionTypeAwareNormalizer;
use ReflectionType;

final class MyNormalizer implements Normalizer, ReflectionTypeAwareNormalizer
{
    public function handleReflectionType(ReflectionType|null $reflectionType): void
    {
        // ...
    }
}
```
after:
```php
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\TypeAwareNormalizer;
use Symfony\Component\TypeInfo\Type;

final class MyNormalizer implements Normalizer, TypeAwareNormalizer
{
    public function handleType(Type|null $type): void
    {
        // ...
    }
}
```

## Lifecycle Hooks

The `PreExtract` and `PostHydrate` method attributes and the `PreHydrate` and
`PostExtract` events have been replaced by a single `LifecycleExtension` with
four method attributes under `Patchlevel\Hydrator\Extension\Lifecycle\Attribute`.
Register the extension on the builder to enable them.

before:
```php
use Patchlevel\Hydrator\MetadataHydrator;

$hydrator = new MetadataHydrator();
```
after:
```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new LifecycleExtension())
    ->build();
```

The hook methods must now be `static` and receive the `$context` array as a
parameter. The two data hooks (`PreHydrate`, `PostExtract`) receive and return
the data array, the two object hooks (`PostHydrate`, `PreExtract`) receive the
object instance.

before:
```php
use Patchlevel\Hydrator\Attribute\PostHydrate;
use Patchlevel\Hydrator\Attribute\PreExtract;

final class Profile
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
after:
```php
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PostHydrate;
use Patchlevel\Hydrator\Extension\Lifecycle\Attribute\PreExtract;

final class Profile
{
    /** @param array<string, mixed> $context */
    #[PostHydrate]
    public static function postHydrate(object $object, array $context): void
    {
        // do something
    }

    /** @param array<string, mixed> $context */
    #[PreExtract]
    public static function preExtract(object $object, array $context): void
    {
        // do something
    }
}
```

## Events

The `PreHydrate` and `PostExtract` events that were dispatched through
`symfony/event-dispatcher` are gone, together with the `eventDispatcher`
constructor argument of the hydrator. Global listeners that ran for every class
are now expressed as a `Middleware`. A middleware wraps the whole hydrate and
extract process: adjust the data before calling `$stack->next()` to replace a
`PreHydrate` listener, and adjust the returned data afterwards to replace a
`PostExtract` listener.

before:
```php
use Patchlevel\Hydrator\Event\PostExtract;
use Patchlevel\Hydrator\Event\PreHydrate;
use Patchlevel\Hydrator\MetadataHydrator;
use Symfony\Component\EventDispatcher\EventDispatcher;

$eventDispatcher = new EventDispatcher();

$eventDispatcher->addListener(
    PreHydrate::class,
    static function (PreHydrate $event): void {
        // adjust $event->data before hydration
    },
);

$eventDispatcher->addListener(
    PostExtract::class,
    static function (PostExtract $event): void {
        // adjust $event->data after extraction
    },
);

$hydrator = new MetadataHydrator(eventDispatcher: $eventDispatcher);
```
after:
```php
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;

final class MyMiddleware implements Middleware
{
    /**
     * @param ClassMetadata<T>     $metadata
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(ClassMetadata $metadata, array $data, array $context, Stack $stack): object
    {
        // adjust $data before hydration (was the PreHydrate event)

        return $stack->next()->hydrate($metadata, $data, $context, $stack);
    }

    /**
     * @param ClassMetadata<T>     $metadata
     * @param T                    $object
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     *
     * @template T of object
     */
    public function extract(ClassMetadata $metadata, object $object, array $context, Stack $stack): array
    {
        $data = $stack->next()->extract($metadata, $object, $context, $stack);

        // adjust $data after extraction (was the PostExtract event)

        return $data;
    }
}
```

Register the middleware on the builder. The default priority places it before
the `TransformMiddleware`, so it runs first on hydrate and last on extract.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->addMiddleware(new MyMiddleware())
    ->build();
```
> [!NOTE]
> The middleware has access to the `ClassMetadata`, so you can decide per class
> whether to act, which is what you previously did by inspecting `$event->metadata`.

## Cryptography

The cryptography support moved out of the core into a dedicated extension under
the `Patchlevel\Hydrator\Extension\Cryptography` namespace, and it is now wired
through a middleware instead of an event subscriber.

### Configuration

Instead of passing a `PayloadCryptographer` to the hydrator constructor, register
the `CryptographyExtension` on the builder. The cryptographer is now the
`BaseCryptographer`, created with `createWithOpenssl()`.

before:
```php
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;
use Patchlevel\Hydrator\Cryptography\Store\InMemoryCipherKeyStore;
use Patchlevel\Hydrator\MetadataHydrator;

$cipherKeyStore = new InMemoryCipherKeyStore();
$cryptographer = PersonalDataPayloadCryptographer::createWithDefaultSettings($cipherKeyStore);
$hydrator = new MetadataHydrator(cryptographer: $cryptographer);
```
after:
```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Cryptography\Store\InMemoryCipherKeyStore;
use Patchlevel\Hydrator\StackHydratorBuilder;

$cipherKeyStore = new InMemoryCipherKeyStore();

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new CryptographyExtension(BaseCryptographer::createWithOpenssl($cipherKeyStore)))
    ->build();
```

### PersonalData attribute

The `PersonalData` attribute has been renamed to `SensitiveData` and moved into
the cryptography extension namespace. This makes clear it is not limited to the
personal data of users.

before:
```php
use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

final class EmailChanged
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
after:
```php
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $profileId,
        #[SensitiveData]
        public readonly string|null $email,
    ) {
    }
}
```

### DataSubjectId attribute

`DataSubjectId` moved from `Patchlevel\Hydrator\Attribute\DataSubjectId` to
`Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId`. You can now
name a subject id and reference it from a field with `subjectIdName`, which lets
you use multiple subjects in one class. The default name is `default`.

before:
```php
use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

final class ProfilesMerged
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $sourceProfileId,
        #[PersonalData]
        public readonly string|null $sourceEmail,
    ) {
    }
}
```
after:
```php
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

final class ProfilesMerged
{
    public function __construct(
        #[DataSubjectId(name: 'source')]
        public readonly string $sourceProfileId,
        #[SensitiveData(subjectIdName: 'source')]
        public readonly string|null $sourceEmail,
    ) {
    }
}
```

### Moved classes

The remaining cryptography classes moved from `Patchlevel\Hydrator\Cryptography`
into `Patchlevel\Hydrator\Extension\Cryptography`. Update the imports for, among
others:

* `Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore` to `Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore`
* `Patchlevel\Hydrator\Cryptography\Store\InMemoryCipherKeyStore` to `Patchlevel\Hydrator\Extension\Cryptography\Store\InMemoryCipherKeyStore`
* `Patchlevel\Hydrator\Cryptography\Cipher\Cipher` to `Patchlevel\Hydrator\Extension\Cryptography\Cipher\Cipher`
* `Patchlevel\Hydrator\Cryptography\Cipher\OpensslCipher` to `Patchlevel\Hydrator\Extension\Cryptography\Cipher\OpensslCipher`

The `CipherKeyStore` interface also changed: keys are now stored and looked up
through `currentKeyFor()`, `get()`, `store()`, `remove()` and
`removeWithSubjectId()`, and a `CipherKey` now carries its own id.

> [!WARNING]
> Removing a cipher key is irreversible. The encrypted data can never be decrypted
> again, that is the point of crypto-shredding, but make sure it is what you want.

## Dependencies

The `symfony/event-dispatcher` dependency has been dropped, since the lifecycle
hooks and the cryptography no longer use it. If you relied on it transitively
through this package, require it explicitly in your own `composer.json`.
