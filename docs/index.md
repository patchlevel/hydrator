# Hydrator

This library enables seamless hydration of objects to arrays - and back again.
It is optimized for both developer experience and performance and works with `final`,
`readonly` classes, constructor property promotion and deeply nested structures.

Hydration is handled through [normalizers](normalizer.md), especially for complex data types.
The library automatically determines the appropriate normalizer based on the property type
and attributes, so in most cases no manual configuration is needed.
And if customization is required, it can be done easily using attributes.

## Features

* Extract objects to arrays and [hydrate](hydrator.md) them back, without calling the constructor.
* Automatic [normalizer](normalizer.md) resolution for enums, date types, collections, array shapes and nested objects.
* Rename or exclude fields with [attributes](hydrator.md).
* [Lazy hydration](lazy.md) of objects with PHP 8.4 lazy proxies.
* Pluggable [guessers](guesser.md) to pick normalizers for your own value objects.
* [Extensions](extensions.md) with middlewares and metadata enrichers to hook into the process.
* [Lifecycle hooks](lifecycle-hooks.md) before extracting and after hydrating.
* Encrypt and decrypt sensitive data with the [cryptography](cryptography.md) extension (crypto-shredding).
* [Cache](caching.md) the metadata with any PSR-6 or PSR-16 cache.

## Installation

```bash
composer require patchlevel/hydrator
```

## Integration

* [Event Sourcing](https://github.com/patchlevel/event-sourcing) - the hydrator powers the storage and retrieval of thousands of events and aggregates.
:::tip
New here? Start with the [getting started guide](getting-started.md) and build your first hydrator in a few minutes.
:::
