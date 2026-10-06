<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

/**
 * How the value of a property is converted between its object and its normalized form.
 *
 * @internal
 */
enum ValueKind
{
    /** No normalizer, the value is copied as is. */
    case Raw;

    /** A normalizer which is called at runtime. */
    case Normalizer;

    /** An object normalizer, the generated transformer of the nested class can be called directly. */
    case NestedObject;

    /** An array normalizer of nested objects, the nested code can be called directly for each item. */
    case NestedArray;
}
