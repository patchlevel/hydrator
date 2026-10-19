<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Mapper;

use Patchlevel\Hydrator\Mapper\ChainPropertyFilter;
use Patchlevel\Hydrator\Mapper\PropertyFilter;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DefaultDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainPropertyFilter::class)]
final class ChainPropertyFilterTest extends TestCase
{
    public function testWithoutFilteredProperties(): void
    {
        $metadata = (new AttributeMetadataFactory())->metadata(DefaultDto::class);
        $filter = new ChainPropertyFilter([$this->filter(null), $this->filter(null)]);

        self::assertNull($filter->filter($metadata, []));
    }

    public function testKeepsThePropertiesOfASingleFilter(): void
    {
        $metadata = (new AttributeMetadataFactory())->metadata(DefaultDto::class);
        $properties = ['name' => $metadata->properties['name']];
        $filter = new ChainPropertyFilter([$this->filter(null), $this->filter($properties), $this->filter(null)]);

        self::assertSame($properties, $filter->filter($metadata, []));
    }

    public function testKeepsThePropertiesEveryFilterKeeps(): void
    {
        $metadata = (new AttributeMetadataFactory())->metadata(DefaultDto::class);
        $properties = $metadata->properties;

        $filter = new ChainPropertyFilter([
            $this->filter(['name' => $properties['name'], 'email' => $properties['email']]),
            $this->filter(null),
            $this->filter(['email' => $properties['email'], 'admin' => $properties['admin']]),
        ]);

        self::assertSame(['email' => $properties['email']], $filter->filter($metadata, []));
    }

    /** @param array<string, PropertyMetadata>|null $properties */
    private function filter(array|null $properties): PropertyFilter
    {
        $filter = $this->createStub(PropertyFilter::class);
        $filter->method('filter')->willReturn($properties);

        return $filter;
    }
}
