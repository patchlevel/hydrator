[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fhydrator%2F2.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/hydrator/2.0.x)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/hydrator/v)](//packagist.org/packages/patchlevel/hydrator)
[![License](https://poser.pugx.org/patchlevel/hydrator/license)](//packagist.org/packages/patchlevel/hydrator)

# Hydrator

"A library for seamless hydration of objects to arrays - and back again,
optimized for developer experience and performance."

## Features

* Extract objects to arrays and hydrate them back, without calling the constructor
* Works with `final`, `readonly` classes, property promotion and deeply nested structures
* Automatic normalizer resolution for enums, date types, collections, array shapes and objects
* Rename or exclude fields with attributes
* Lazy hydration of objects with PHP 8.4 lazy proxies
* Pluggable guessers and extensions to customize the process
* Safe usage of Personal Data with crypto-shredding
* Metadata caching with any PSR-6 or PSR-16 cache
* Developer experience oriented and fully typed
* and much more...

## Installation

```bash
composer require patchlevel/hydrator
```

## Documentation

* Latest [Docs](https://patchlevel.dev/docs/hydrator/latest)
* Related [Blog](https://patchlevel.dev/blog)

## Integration

* [Event Sourcing](https://github.com/patchlevel/event-sourcing)

## Contributing

We are open to contributions as long as they are in line with
our [BC-Policy](https://patchlevel.dev/our-backward-compatibility-promise).

Also note that the `composer.lock` is always generated with the newest supported PHP version as this is the version our tools run in the CI.
