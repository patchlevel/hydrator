<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Hydrator;

use Patchlevel\Hydrator\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\Hydrator\TypeMismatch;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\WrongNormalizer;
use PHPUnit\Framework\TestCase;

final class MetadataEventHydratorTest extends TestCase
{
    private MetadataHydrator $hydrator;

    public function setUp(): void
    {
        $this->hydrator = new MetadataHydrator(new AttributeMetadataFactory());
    }

    public function testExtract(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        self::assertEquals(
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            $this->hydrator->extract($event)
        );
    }

    public function testExtractWithInheritance(): void
    {
        $event = new ParentDto(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        self::assertEquals(
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            $this->hydrator->extract($event)
        );
    }

    public function testHydrate(): void
    {
        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $event = $this->hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de']
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithInheritance(): void
    {
        $expected = new ParentDto(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $event = $this->hydrator->hydrate(
            ParentDto::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de']
        );

        self::assertEquals($expected, $event);
    }

    public function testWithTypeMismatch(): void
    {
        $this->expectException(TypeMismatch::class);

        $this->hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => null, 'email' => null]
        );
    }

    public function testDenormalizationFailure(): void
    {
        $this->expectException(DenormalizationFailure::class);

        $this->hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => 123, 'email' => 123]
        );
    }

    public function testNormalizationFailure(): void
    {
        $this->expectException(NormalizationFailure::class);

        $this->hydrator->extract(
            new WrongNormalizer(true)
        );
    }
}
