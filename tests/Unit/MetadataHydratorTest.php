<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\ClassNotSupported;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Event\PostExtract;
use Patchlevel\Hydrator\Event\PreHydrate;
use Patchlevel\Hydrator\Guesser\Guesser;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\NormalizationMissing;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle1Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle2Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle3Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DtoWithHooks;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerBrokenDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerWithIterablesDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerWithNullableDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LazyProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NormalizerInBaseClassDefinedDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWithNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWrapper;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use Patchlevel\Hydrator\Tests\Unit\Fixture\StatusWithNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\WrongNormalizer;
use Patchlevel\Hydrator\TypeMismatch;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\TypeInfo\Type\ObjectType;

final class MetadataHydratorTest extends TestCase
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

    public function testExtractWithInferNormalizer2(): void
    {
        $result = $this->hydrator->extract(
            new InferNormalizerWithNullableDto(
                null,
                null,
                profileId: ProfileId::fromString('1'),
            ),
        );

        self::assertEquals(
            [
                'status' => null,
                'dateTimeImmutable' => null,
                'dateTime' => null,
                'dateTimeZone' => null,
                'profileId' => '1',
            ],
            $result,
        );
    }

    public function testExtractWithInferNormalizerFailed(): void
    {
        $this->expectException(NormalizationMissing::class);
        $this->hydrator->extract(
            new InferNormalizerBrokenDto(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('info@patchlevel.de'),
                ),
            ),
        );
    }

    public function testExtractWithHooks(): void
    {
        $data = $this->hydrator->extract(new DtoWithHooks());

        self::assertEquals(['postHydrateCalled' => false, 'preExtractCalled' => true], $data);
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

    public function testHydrateUnknownClass(): void
    {
        $this->expectException(ClassNotSupported::class);

        $this->hydrator->hydrate(
            'Unknown',
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );
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

        $cryptographer = $this->createMock(PayloadCryptographer::class);
        $cryptographer
            ->expects($this->once())
            ->method('decrypt')
            ->with($metadataFactory->metadata(ProfileCreated::class), $encryptedPayload)
            ->willReturn($payload);

        $hydrator = new MetadataHydrator($metadataFactory, $cryptographer);

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

        $cryptographer = $this->createMock(PayloadCryptographer::class);
        $cryptographer
            ->expects($this->once())
            ->method('encrypt')
            ->with($metadataFactory->metadata(ProfileCreated::class), $payload)
            ->willReturn($encryptedPayload);

        $hydrator = new MetadataHydrator($metadataFactory, $cryptographer);

        $return = $hydrator->extract($object);

        self::assertSame($encryptedPayload, $return);
    }

    public function testPreHydrate(): void
    {
        $object = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $payload = ['profileId' => '1', 'email' => 'info@patchlevel.de'];
        $encryptedPayload = ['profileId' => '1', 'email' => 'encrypted'];

        $metadataFactory = new AttributeMetadataFactory();

        $event = new PreHydrate(
            $encryptedPayload,
            $metadataFactory->metadata(ProfileCreated::class),
        );

        $eventReturn = new PreHydrate(
            $payload,
            $metadataFactory->metadata(ProfileCreated::class),
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($event)
            ->willReturn($eventReturn);

        $hydrator = new MetadataHydrator($metadataFactory, eventDispatcher: $eventDispatcher);

        $return = $hydrator->hydrate(ProfileCreated::class, $encryptedPayload);

        self::assertEquals($object, $return);
    }

    public function testPostExtract(): void
    {
        $object = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $payload = ['profileId' => '1', 'email' => 'info@patchlevel.de'];
        $encryptedPayload = ['profileId' => '1', 'email' => 'encrypted'];

        $metadataFactory = new AttributeMetadataFactory();

        $event = new PostExtract(
            $payload,
            $metadataFactory->metadata(ProfileCreated::class),
        );

        $eventReturn = new PostExtract(
            $encryptedPayload,
            $metadataFactory->metadata(ProfileCreated::class),
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with($event)
            ->willReturn($eventReturn);

        $hydrator = new MetadataHydrator($metadataFactory, eventDispatcher: $eventDispatcher);

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
            [StatusWithNormalizer::Draft],
            [StatusWithNormalizer::Draft],
            [StatusWithNormalizer::Draft],
            [
                'foo' => new Skill('php'),
                'bar' => new Skill('symfony'),
            ],
            [
                'foo' => 'php',
                'bar' => 15,
                'baz' => ['test'],
            ],
        );

        $event = $this->hydrator->hydrate(
            NormalizerInBaseClassDefinedDto::class,
            [
                'status' => 'draft',
                'profileCreated' => ['profileId' => '1', 'email' => 'info@patchlevel.de'],
                'defaultArray' => ['draft'],
                'listArray' => ['draft'],
                'iterableArray' => ['draft'],
                'skillsHashMap' => ['foo' => ['name' => 'php'], 'bar' => ['name' => 'symfony']],
                'jsonArray' => ['foo' => 'php', 'bar' => 15, 'baz' => ['test']],
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

    public function testHydrateWithInferNormalizerAndNullableProperties(): void
    {
        $expected = new InferNormalizerWithNullableDto(
            null,
            null,
            null,
            null,
        );

        $event = $this->hydrator->hydrate(
            InferNormalizerWithNullableDto::class,
            [
                'status' => null,
                'dateTimeImmutable' => null,
                'dateTime' => null,
                'dateTimeZone' => null,
            ],
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithInferNormalizerWitIterables(): void
    {
        $expected = new InferNormalizerWithIterablesDto(
            [Status::Draft],
            [Status::Draft],
            [Status::Draft],
            [
                'foo' => Status::Draft,
                'bar' => Status::Draft,
            ],
            [
                'foo' => [Status::Draft],
                'bar' => [Status::Draft],
            ],
            [
                'foo' => 'php',
                'bar' => 15,
                'baz' => ['test'],
            ],
            [
                'status' => Status::Draft,
                'other' => [Status::Draft],
            ],
        );

        $event = $this->hydrator->hydrate(
            InferNormalizerWithIterablesDto::class,
            [
                'defaultArray' => ['draft'],
                'listArray' => ['draft'],
                'iterableArray' => ['draft'],
                'hashMap' => ['foo' => 'draft', 'bar' => 'draft'],
                'nested' => ['foo' => ['draft'], 'bar' => ['draft']],
                'jsonArray' => ['foo' => 'php', 'bar' => 15, 'baz' => ['test']],
                'shapeArray' => ['status' => 'draft', 'other' => ['draft']],
            ],
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithInferNormalizerFailed(): void
    {
        $this->expectException(TypeMismatch::class);
        $this->hydrator->hydrate(
            InferNormalizerBrokenDto::class,
            [
                'profileCreated' => ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            ],
        );
    }

    public function testHydrateWithHooks(): void
    {
        $object = $this->hydrator->hydrate(DtoWithHooks::class, ['postHydrateCalled' => false, 'preExtractCalled' => false]);

        self::assertEquals(true, $object->postHydrateCalled);
        self::assertEquals(false, $object->preExtractCalled);
    }

    #[RequiresPhp('>=8.4')]
    public function testLazyHydrate(): void
    {
        $event = $this->hydrator->hydrate(
            LazyProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );

        $expected = new LazyProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        self::assertInstanceOf(LazyProfileCreated::class, $event);

        $reflection = new ReflectionClass(LazyProfileCreated::class);

        self::assertTrue($reflection->isUninitializedLazyObject($event));

        $reflection->initializeLazyObject($event);

        self::assertEquals($expected, $event);
    }

    #[RequiresPhp('<8.4')]
    public function testLazyNotSupported(): void
    {
        $event = $this->hydrator->hydrate(
            LazyProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );

        $expected = new LazyProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        self::assertEquals($expected, $event);
    }

    #[RequiresPhp('>=8.4')]
    public function testLazyExtract(): void
    {
        $event = $this->hydrator->hydrate(
            LazyProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );

        $data = $this->hydrator->extract($event);

        self::assertEquals(['profileId' => '1', 'email' => 'info@patchlevel.de'], $data);
    }

    public function testCreate(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $hydrator = MetadataHydrator::create(
            [
                new class implements Guesser
                {
                    public function guess(ObjectType $type): Normalizer|null
                    {
                        return null;
                    }
                },
            ],
            $eventDispatcher,
        );

        self::assertInstanceOf(MetadataHydrator::class, $hydrator);
    }
}
