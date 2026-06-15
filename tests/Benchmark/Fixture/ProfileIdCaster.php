<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Benchmark\Fixture;

use Attribute;
use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\PropertyCaster;
use EventSauce\ObjectHydrator\PropertySerializer;
use InvalidArgumentException;
use Patchlevel\Hydrator\Normalizer\Normalizer;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ProfileIdCaster implements PropertyCaster, PropertySerializer
{
    public function cast(mixed $value, ObjectMapper $hydrator): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException();
        }

        return ProfileId::fromString($value);
    }

    public function serialize(mixed $value, ObjectMapper $hydrator): mixed
    {
        if (!$value instanceof ProfileId) {
            throw new InvalidArgumentException();
        }

        return $value->toString();
    }
}
