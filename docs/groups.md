# Groups

Often you don't want to extract every property of an object, for example
when the same class is exposed to the public and to admins, or when only a
part of the data should be hydrated. The `GroupsExtension` lets you assign
properties to groups and select the groups via the context, similar to the
groups of the Symfony Serializer.

## Setup

Register the `GroupsExtension` on the builder:

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new GroupsExtension())
    ->build();
```
## Define groups

Mark the properties with the `Groups` attribute. It accepts a single group or
a list of groups.

```php
use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;

final class User
{
    public function __construct(
        #[Groups(['public', 'admin'])]
        public string $id,
        #[Groups('public')]
        public string $name,
        #[Groups('admin')]
        public string $email,
        public string $passwordHash,
    ) {
    }
}
```
## Select groups

Pass the groups with the `GroupsExtension::GROUPS` context key, either as a
string or as a list of strings. Only properties that are in at least one of
the given groups are extracted.

```php
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;

$hydrator->extract($user, [GroupsExtension::GROUPS => 'public']);
// ['id' => '...', 'name' => '...']

$hydrator->extract($user, [GroupsExtension::GROUPS => ['public', 'admin']]);
// ['id' => '...', 'name' => '...', 'email' => '...']
```
Without groups in the context, all properties are used, so the extension does
not change anything until you ask for it. Properties without the `Groups`
attribute are excluded as soon as groups are selected. Use the wildcard group
`GroupsExtension::ALL` (`*`) to include every property again.

```php
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;

$hydrator->extract($user, [GroupsExtension::GROUPS => GroupsExtension::ALL]);
```
:::note
The context is passed on to nested objects, so their properties are filtered
by the same groups. Make sure nested classes have the `Groups` attribute as
well, otherwise they are extracted as an empty array.
:::

## Ignore groups

The other way around, `GroupsExtension::IGNORED_GROUPS` excludes every
property that is in at least one of the given groups. Properties without the
`Groups` attribute are kept, so you can hide a few properties without tagging
all the others.

```php
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;

$hydrator->extract($user, [GroupsExtension::IGNORED_GROUPS => 'admin']);
// ['name' => '...', 'passwordHash' => '...']
```
Both options can be combined. The ignored groups win, so a property that is in
a selected and in an ignored group is excluded.

```php
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;

$hydrator->extract($user, [
    GroupsExtension::GROUPS => ['public', 'admin'],
    GroupsExtension::IGNORED_GROUPS => 'admin',
]);
// ['name' => '...']
```
## Circular references

Since properties outside the selected groups are never read, groups are also a
way to break circular references. Put the back reference into a different
group than the forward reference:

```php
use Patchlevel\Hydrator\Extension\Groups\Attribute\Groups;
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;

final class Author
{
    #[Groups(['author', 'book'])]
    public string $name;

    #[Groups('author')]
    public Book $book;
}

final class Book
{
    #[Groups(['author', 'book'])]
    public string $title;

    #[Groups('book')]
    public Author $author;
}

$hydrator->extract($author, [GroupsExtension::GROUPS => 'author']);
// ['name' => '...', 'book' => ['title' => '...']]
```
:::warning
Without groups, or with the wildcard group, every property is followed again
and a `CircularReference` exception is thrown as usual.
:::

## Hydrate with groups

Groups also work while hydrating. Fields of properties outside the selected
groups are ignored, even if they are present in the data. Promoted properties
with a default value fall back to it, all others stay uninitialized.

```php
use Patchlevel\Hydrator\Extension\Groups\GroupsExtension;

$user = $hydrator->hydrate(
    User::class,
    ['id' => '1', 'name' => 'John', 'email' => 'john@example.com'],
    [GroupsExtension::GROUPS => 'public'],
);
```
:::tip
Combine it with `Hydrator::OBJECT_TO_POPULATE` to update only the properties
of a specific group on an existing object.
:::

## Learn more

* [How extensions and middlewares work](extensions.md)
* [How to use the hydrator](hydrator.md)
* [How to ignore a property completely](hydrator.md#ignore-properties)
