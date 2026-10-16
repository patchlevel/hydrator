<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Transformer;

use Patchlevel\Hydrator\ClassExtractor;
use Patchlevel\Hydrator\ClassHydrator;

/**
 * Maps the data of one class to an object and back, it is the end of the chain the hydrator builds for the class. If
 * nothing else runs for the class, the transformer is the whole chain.
 *
 * The context of hydrate() may contain an object to populate, see {@see \Patchlevel\Hydrator\Hydrator::OBJECT_TO_POPULATE}.
 *
 * @template T of object
 * @extends ClassHydrator<T>
 * @extends ClassExtractor<T>
 */
interface ClassTransformer extends ClassHydrator, ClassExtractor
{
}
