# Guesser

When a property is an object and no [normalizer](normalizer.md) attribute is
found on the property or class, the hydrator asks its guessers which normalizer
to use. Guessers are the right tool when you can't put an attribute on the
class itself, for example for third-party classes.

## Built-in guesser

The `BuiltInGuesser` is registered by the `CoreExtension`. It resolves backed
enums to the `EnumNormalizer`, the date types (`DateTimeImmutable`, `DateTime`,
`DateTimeZone`, `DateInterval`) to their normalizers and falls back to the
`ObjectNormalizer` for everything else.

## Custom guesser

A guesser implements the `Guesser` interface. It receives the resolved object
type and returns a normalizer or `null` if it is not responsible.

```php
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Symfony\Component\TypeInfo\Type\ObjectType;

final class NameGuesser implements Guesser
{
    public function guess(ObjectType $type): Normalizer|null
    {
        return match ($type->getClassName()) {
            Name::class => new NameNormalizer(),
            default => null,
        };
    }
}
```

To use the guesser, add it to the builder:

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->addGuesser(new NameGuesser())
    ->build();
```
:::note
The guessers are queried in order of their priority, and the first match wins.
The built-in guesser is registered with priority `-64`, so your own guessers run
before the fallback to the `ObjectNormalizer`.
:::

## Mapped guesser

For the common case of a simple class-to-normalizer mapping, you don't need to
write your own guesser class, use the `MappedGuesser`:

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Guesser\MappedGuesser;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->addGuesser(new MappedGuesser([
        Name::class => NameNormalizer::class,
        Email::class => EmailNormalizer::class,
    ]))
    ->build();
```
:::note
The `MappedGuesser` instantiates the normalizer class without arguments, so the
normalizer must have a constructor without required parameters.
:::

## Learn more

* [How normalizers are resolved](normalizer.md)
* [How to configure the builder](extensions.md)
* [How to use the hydrator](hydrator.md)
