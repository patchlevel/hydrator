<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle1Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle2Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle3Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerBrokenDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NormalizerInBaseClassDefinedDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWithNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWrapper;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use Patchlevel\Hydrator\Tests\Unit\Fixture\StatusWithNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\WrongNormalizer;
use Patchlevel\Hydrator\TypeMismatch;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class MetadataHydratorTest extends TestCase
{
    use ProphecyTrait;

    private MetadataHydrator $hydrator;

    public function setUp(): void
    {
        $this->hydrator = new MetadataHydrator();
    }

    public function testExtract(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        self::assertEquals(
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            $this->hydrator->extract($event),
        );
    }

    public function testExtractWithInheritance(): void
    {
        $event = new ParentDto(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        self::assertEquals(
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            $this->hydrator->extract($event),
        );
    }

    public function testExtractWithHydratorAwareNormalizer(): void
    {
        $event = new ProfileCreatedWrapper(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        self::assertEquals(
            ['event' => ['profileId' => '1', 'email' => 'info@patchlevel.de']],
            $this->hydrator->extract($event),
        );
    }

    public function testExtractCircularReference(): void
    {
        $this->expectException(CircularReference::class);
        $this->expectExceptionMessage('Circular reference detected: Patchlevel\Hydrator\Tests\Unit\Fixture\Circle1Dto -> Patchlevel\Hydrator\Tests\Unit\Fixture\Circle2Dto -> Patchlevel\Hydrator\Tests\Unit\Fixture\Circle3Dto -> Patchlevel\Hydrator\Tests\Unit\Fixture\Circle1Dto');

        $dto1 = new Circle1Dto();
        $dto2 = new Circle2Dto();
        $dto3 = new Circle3Dto();

        $dto1->to = $dto2;
        $dto2->to = $dto3;
        $dto3->to = $dto1;

        $this->hydrator->extract($dto1);
    }

    public function testHydrate(): void
    {
        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $event = $this->hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithDefaults(): void
    {
        $object = $this->hydrator->hydrate(
            DefaultDto::class,
            ['name' => 'test'],
        );

        self::assertEquals('test', $object->name);
        self::assertEquals(new Email('info@patchlevel.de'), $object->email);
        self::assertEquals(true, $object->admin);
    }

    public function testHydrateWithInheritance(): void
    {
        $expected = new ParentDto(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $event = $this->hydrator->hydrate(
            ParentDto::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithHydratorAwareNormalizer(): void
    {
        $expected = new ProfileCreatedWrapper(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $event = $this->hydrator->hydrate(
            ProfileCreatedWrapper::class,
            [
                'event' => ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            ],
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithTypeMismatch(): void
    {
        $this->expectException(TypeMismatch::class);

        $this->hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => null, 'email' => null],
        );
    }

    public function testDenormalizationFailure(): void
    {
        $this->expectException(DenormalizationFailure::class);

        $this->hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => 123, 'email' => 123],
        );
    }

    public function testNormalizationFailure(): void
    {
        $this->expectException(NormalizationFailure::class);

        $this->hydrator->extract(
            new WrongNormalizer(true),
        );
    }

    public function testDecrypt(): void
    {
        $object = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $payload = ['profileId' => '1', 'email' => 'info@patchlevel.de'];
        $encryptedPayload = ['profileId' => '1', 'email' => 'encrypted'];

        $metadataFactory = new AttributeMetadataFactory();

        $cryptographer = $this->prophesize(PayloadCryptographer::class);
        $cryptographer
            ->decrypt($metadataFactory->metadata(ProfileCreated::class), $encryptedPayload)
            ->willReturn($payload)
            ->shouldBeCalledOnce();

        $hydrator = new MetadataHydrator($metadataFactory, $cryptographer->reveal());

        $return = $hydrator->hydrate(ProfileCreated::class, $encryptedPayload);

        self::assertEquals($object, $return);
    }

    public function testEncrypt(): void
    {
        $object = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $payload = ['profileId' => '1', 'email' => 'info@patchlevel.de'];
        $encryptedPayload = ['profileId' => '1', 'email' => 'encrypted'];

        $metadataFactory = new AttributeMetadataFactory();

        $cryptographer = $this->prophesize(PayloadCryptographer::class);
        $cryptographer
            ->encrypt($metadataFactory->metadata(ProfileCreated::class), $payload)
            ->willReturn($encryptedPayload)
            ->shouldBeCalledOnce();

        $hydrator = new MetadataHydrator($metadataFactory, $cryptographer->reveal());

        $return = $hydrator->extract($object);

        self::assertSame($encryptedPayload, $return);
    }

    public function testHydrateWithNormalizerInBaseClass(): void
    {
        $expected = new NormalizerInBaseClassDefinedDto(
            StatusWithNormalizer::Draft,
            new ProfileCreatedWithNormalizer(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
            ['foo'],
        );

        $event = $this->hydrator->hydrate(
            NormalizerInBaseClassDefinedDto::class,
            [
                'status' => 'draft',
                'profileCreated' => ['profileId' => '1', 'email' => 'info@patchlevel.de'],
                'array' => ['foo'],
            ],
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithInferNormalizer(): void
    {
        $expected = new InferNormalizerDto(
            Status::Draft,
            new DateTimeImmutable('2015-02-13 22:34:32+01:00'),
            new DateTime('2015-02-13 22:34:32+01:00'),
            new DateTimeZone('EDT'),
            ['foo'],
        );

        $event = $this->hydrator->hydrate(
            InferNormalizerDto::class,
            [
                'status' => 'draft',
                'dateTimeImmutable' => '2015-02-13T22:34:32+01:00',
                'dateTime' => '2015-02-13T22:34:32+01:00',
                'dateTimeZone' => 'EDT',
                'array' => ['foo'],
            ],
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithInferNormalizerFailed(): void
    {
        $this->expectException(TypeMismatch::class);
        $event = $this->hydrator->hydrate(
            InferNormalizerBrokenDto::class,
            [
                'profileCreated' => ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            ],
        );
    }
}
