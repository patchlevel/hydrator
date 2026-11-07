<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Patchlevel\Hydrator\ArrayDataRequired;
use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\ClassNotSupported;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\AllMiddlewaresSkipped;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;
use Patchlevel\Hydrator\MissingMiddlewares;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\Normalizer\HydratorAwareNormalizer;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle1Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle2Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle3Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\CodeBlock;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ContentBlock;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ContextAwareDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DummyMiddleware;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ExplicitArrayShapeNormalizerDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ExplicitObjectNormalizerDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerWithArrayShapeDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerWithIterablesDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerWithNullableDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerWithNullableItemsDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LazyProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NormalizerInBaseClassDefinedDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ObjectMapDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWithInlineNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWithNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWrapper;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Square;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use Patchlevel\Hydrator\Tests\Unit\Fixture\StatusWithInterfaceNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\StatusWithNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\SubId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ValueObject;
use Patchlevel\Hydrator\Tests\Unit\Fixture\WrongNormalizer;
use Patchlevel\Hydrator\TypeMismatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

#[CoversClass(StackHydrator::class)]
#[CoversClass(TransformMiddleware::class)]
final class StackHydratorTest extends TestCase
{
    private StackHydrator $hydrator;

    public function setUp(): void
    {
        $this->hydrator = new StackHydrator();
    }

    public function testMissingMiddlewares(): void
    {
        $this->expectException(MissingMiddlewares::class);
        $this->expectExceptionMessage('Missing middlewares.');

        new StackHydrator(
            new AttributeMetadataFactory(),
            [],
        );
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

    public function testExtractPassesContextToNormalizer(): void
    {
        $dto = new ContextAwareDto('value');

        $data = $this->hydrator->extract($dto, ['prefix' => 'ctx-']);

        self::assertSame(['value' => 'ctx-value'], $data);
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

        // Prevent infinite recursion
        $dummy = new DummyMiddleware();

        $middleware = $this->createMock(Middleware::class);
        $middleware
            ->expects($this->exactly(4))
            ->method('extract')
            ->willReturnCallback($dummy->extract(...));

        $hydrator = new StackHydrator(middlewares: [$middleware, new TransformMiddleware()]);

        $hydrator->extract($dto1);
    }

    public function testExtractWithInferNormalizer(): void
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

    public function testExtractWithContext(): void
    {
        $object = new InferNormalizerDto(
            Status::Draft,
            new DateTimeImmutable('2015-02-13 22:34:32+01:00'),
            new DateTime('2015-02-13 22:34:32+01:00'),
            new DateTimeZone('EDT'),
            ['foo'],
        );

        $expect = [
            'status' => 'draft',
            'dateTimeImmutable' => '2015-02-13T22:34:32+01:00',
            'dateTime' => '2015-02-13T22:34:32+01:00',
            'dateTimeZone' => 'EDT',
            'array' => ['foo'],
        ];

        $middleware = $this->createMock(Middleware::class);
        $middleware
            ->expects($this->once())
            ->method('extract')
            ->with(
                $this->isInstanceOf(ClassMetadata::class),
                $object,
                ['context' => '123'],
                $this->isInstanceOf(Stack::class),
            )->willReturn($expect);

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->addMiddleware($middleware)
            ->build();

        $data = $hydrator->extract($object, ['context' => '123']);

        self::assertEquals($expect, $data);
    }

    #[RequiresPhp('>=8.5')]
    public function testExtractWithInlineNormalizer(): void
    {
        $event = new ProfileCreatedWithInlineNormalizer(
            ProfileId::fromString('1'),
            ValueObject::fromString('foo'),
        );

        self::assertEquals(
            ['profileId' => '1', 'valueObject' => 'foo'],
            $this->hydrator->extract($event),
        );
    }

    public function testExtractWithClassNormalizer(): void
    {
        $data = $this->hydrator->extract(
            ProfileId::fromString('id'),
        );

        self::assertEquals('id', $data);
    }

    public function testExtractBackedEnum(): void
    {
        self::assertSame('draft', $this->hydrator->extract(Status::Draft));
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

    public function testHydratePassesContextToNormalizer(): void
    {
        $event = $this->hydrator->hydrate(
            ContextAwareDto::class,
            ['value' => 'value'],
            ['suffix' => '-ctx'],
        );

        self::assertSame('value-ctx', $event->value);
    }

    public function testHydrateUnknownClass(): void
    {
        $this->expectException(ClassNotSupported::class);
        $this->expectExceptionCode(0);

        $this->hydrator->hydrate(
            'Unknown',
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );
    }

    public function testHydrateWithArrayDataRequired(): void
    {
        $this->expectException(ArrayDataRequired::class);
        $this->expectExceptionMessage('The data for the class "Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated" must be an array. If you want to use another data type, you need to add a normalizer to the class.');

        $this->hydrator->hydrate(
            ProfileCreated::class,
            'foo',
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

    public function testHydrateWithContext(): void
    {
        $expect = new InferNormalizerDto(
            Status::Draft,
            new DateTimeImmutable('2015-02-13 22:34:32+01:00'),
            new DateTime('2015-02-13 22:34:32+01:00'),
            new DateTimeZone('EDT'),
            ['foo'],
        );

        $data = [
            'status' => 'draft',
            'dateTimeImmutable' => '2015-02-13T22:34:32+01:00',
            'dateTime' => '2015-02-13T22:34:32+01:00',
            'dateTimeZone' => 'EDT',
            'array' => ['foo'],
        ];

        $middleware = $this->createMock(Middleware::class);
        $middleware
            ->expects($this->once())
            ->method('hydrate')
            ->with(
                $this->isInstanceOf(ClassMetadata::class),
                $data,
                ['context' => '123'],
                $this->isInstanceOf(Stack::class),
            )->willReturn($expect);

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->addMiddleware($middleware)
            ->build();

        $object = $hydrator->hydrate(InferNormalizerDto::class, $data, ['context' => '123']);

        self::assertEquals($expect, $object);
    }

    public function testHydrateWithObjectToPopulate(): void
    {
        $id = ProfileId::fromString('1');
        $dto = new ProfileCreated($id, Email::fromString('foo@patchlevel.de'));

        $processedDto = $this->hydrator->hydrate(
            $dto::class,
            ['email' => 'other@patchlevel.de'],
            [StackHydrator::OBJECT_TO_POPULATE => $dto],
        );

        self::assertSame($dto, $processedDto);
        self::assertSame($id, $processedDto->profileId);
        self::assertEquals(Email::fromString('other@patchlevel.de'), $processedDto->email);
    }

    #[RequiresPhp('>=8.5')]
    public function testHydrateWithInlineNormalizer(): void
    {
        $expected = new ProfileCreatedWithInlineNormalizer(
            ProfileId::fromString('1'),
            ValueObject::fromString('foo'),
        );

        $event = $this->hydrator->hydrate(
            ProfileCreatedWithInlineNormalizer::class,
            ['profileId' => '1', 'valueObject' => 'foo'],
        );

        self::assertEquals($expected, $event);
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

    public function testRoundTripWithInferNormalizerForNullableCollectionItems(): void
    {
        $object = new InferNormalizerWithNullableItemsDto(
            [new Skill('php'), null],
            ['foo' => Status::Draft, 'bar' => null],
            [new DateTimeImmutable('2015-02-13 22:34:32+01:00'), null],
            ['foo' => [Status::Closed, null]],
        );

        $data = [
            'skills' => [['name' => 'php'], null],
            'statuses' => ['foo' => 'draft', 'bar' => null],
            'dates' => ['2015-02-13T22:34:32+01:00', null],
            'nested' => ['foo' => ['closed', null]],
        ];

        self::assertSame($data, $this->hydrator->extract($object));
        self::assertEquals(
            $object,
            $this->hydrator->hydrate(InferNormalizerWithNullableItemsDto::class, $data),
        );
    }

    public function testRoundTripWithArrayShape(): void
    {
        $object = new InferNormalizerWithArrayShapeDto([
            'height' => 180,
            'nickname' => null,
            'skills' => [new Skill('php'), new Skill('go')],
            'status' => null,
            'createdAt' => new DateTimeImmutable('2015-02-13 22:34:32+01:00'),
        ]);

        $data = [
            'profile' => [
                'height' => 180,
                'nickname' => null,
                'skills' => [['name' => 'php'], ['name' => 'go']],
                'status' => null,
                'createdAt' => '2015-02-13T22:34:32+01:00',
            ],
        ];

        self::assertSame($data, $this->hydrator->extract($object));
        self::assertEquals($object, $this->hydrator->hydrate(InferNormalizerWithArrayShapeDto::class, $data));
    }

    public function testRoundTripWithExplicitArrayShapeNormalizerAndFieldMissingInDocblock(): void
    {
        $object = new ExplicitArrayShapeNormalizerDto([
            'status' => Status::Draft,
            'skill' => new Skill('php'),
        ]);

        $data = [
            'shape' => [
                'status' => 'draft',
                'skill' => ['name' => 'php'],
            ],
        ];

        self::assertSame($data, $this->hydrator->extract($object));
        self::assertEquals(
            $object,
            $this->hydrator->hydrate(ExplicitArrayShapeNormalizerDto::class, $data),
        );
    }

    public function testHydrateWithClassNormalizer(): void
    {
        $object = $this->hydrator->hydrate(
            ProfileId::class,
            'id',
        );

        self::assertEquals(ProfileId::fromString('id'), $object);
    }

    public function testHydrateBackedEnum(): void
    {
        self::assertSame(Status::Draft, $this->hydrator->hydrate(Status::class, 'draft'));
    }

    public function testBackedEnumWithEnumNormalizerOnTheEnum(): void
    {
        self::assertSame('draft', $this->hydrator->extract(StatusWithNormalizer::Draft));
        self::assertSame(StatusWithNormalizer::Draft, $this->hydrator->hydrate(StatusWithNormalizer::class, 'draft'));
    }

    public function testBackedEnumWithEnumNormalizerFromAnInterface(): void
    {
        self::assertSame('draft', $this->hydrator->extract(StatusWithInterfaceNormalizer::Draft));
        self::assertSame(
            StatusWithInterfaceNormalizer::Draft,
            $this->hydrator->hydrate(StatusWithInterfaceNormalizer::class, 'draft'),
        );
    }

    public function testRoundTripWithClassNormalizerFromAbstractParent(): void
    {
        $data = $this->hydrator->extract(SubId::fromString('1'));

        self::assertSame('1', $data);
        self::assertEquals(SubId::fromString('1'), $this->hydrator->hydrate(SubId::class, $data));
    }

    public function testExtractWithObjectMapOnInterfaceAndAbstractClass(): void
    {
        $object = new ObjectMapDto(
            new ContentBlock('foo'),
            new ContentBlock('bar'),
            [new ContentBlock('baz'), new CodeBlock('echo 1;')],
            new Circle(5),
        );

        self::assertSame(
            [
                'block' => ['text' => 'foo', '_type' => 'content'],
                'contentBlock' => ['text' => 'bar', '_type' => 'content'],
                'blocks' => [
                    ['text' => 'baz', '_type' => 'content'],
                    ['code' => 'echo 1;', '_type' => 'code'],
                ],
                'shape' => ['radius' => 5, '_type' => 'circle'],
            ],
            $this->hydrator->extract($object),
        );
    }

    public function testHydrateWithObjectMapOnInterfaceAndAbstractClass(): void
    {
        $object = $this->hydrator->hydrate(
            ObjectMapDto::class,
            [
                'block' => ['_type' => 'code', 'code' => 'echo 1;'],
                'contentBlock' => ['_type' => 'content', 'text' => 'bar'],
                'blocks' => [
                    ['_type' => 'content', 'text' => 'baz'],
                    ['_type' => 'code', 'code' => 'echo 2;'],
                ],
                'shape' => ['_type' => 'square', 'size' => 3],
            ],
        );

        self::assertEquals(
            new ObjectMapDto(
                new CodeBlock('echo 1;'),
                new ContentBlock('bar'),
                [new ContentBlock('baz'), new CodeBlock('echo 2;')],
                new Square(3),
            ),
            $object,
        );
    }

    public function testExtractClassOfObjectMap(): void
    {
        self::assertSame(['text' => 'foo'], $this->hydrator->extract(new ContentBlock('foo')));
        self::assertSame(['radius' => 5], $this->hydrator->extract(new Circle(5)));
    }

    public function testHydrateClassOfObjectMap(): void
    {
        self::assertEquals(
            new ContentBlock('foo'),
            $this->hydrator->hydrate(ContentBlock::class, ['text' => 'foo']),
        );

        self::assertEquals(
            new Circle(5),
            $this->hydrator->hydrate(Circle::class, ['radius' => 5]),
        );
    }

    public function testRoundTripWithExplicitObjectNormalizerAndClassNormalizer(): void
    {
        $object = new ExplicitObjectNormalizerDto(ProfileId::fromString('1'));

        $data = $this->hydrator->extract($object);

        self::assertEquals(['profileId' => '1'], $data);
        self::assertEquals($object, $this->hydrator->hydrate(ExplicitObjectNormalizerDto::class, $data));
    }

    public function testHydrateWithExplicitObjectNormalizerAndNonArrayData(): void
    {
        try {
            $this->hydrator->hydrate(
                ProfileCreatedWrapper::class,
                ['event' => 'foo'],
            );

            self::fail('DenormalizationFailure was not thrown');
        } catch (DenormalizationFailure $e) {
            self::assertInstanceOf(ArrayDataRequired::class, $e->getPrevious());
        }
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

    public function testMetadata(): void
    {
        $metadata = $this->hydrator->metadata(ProfileCreated::class);

        self::assertSame(ProfileCreated::class, $metadata->className);

        $metadata2 = $this->hydrator->metadata(ProfileCreated::class);
        self::assertSame($metadata, $metadata2);
    }

    public function testMetadataWithHydratorAwareNormalizer(): void
    {
        $metadata = $this->hydrator->metadata(ProfileCreatedWrapper::class);

        $propertyMetadata = $metadata->propertyForField('event');
        $normalizer = $propertyMetadata->normalizer;

        self::assertInstanceOf(HydratorAwareNormalizer::class, $normalizer);

        $reflection = new ReflectionProperty($normalizer, 'hydrator');
        self::assertSame($this->hydrator, $reflection->getValue($normalizer));
    }

    public function testSkippableMiddlewareIsSkipped(): void
    {
        $middleware = $this->createMock(SkippableMiddleware::class);
        $middleware
            ->expects($this->once())
            ->method('skip')
            ->willReturn(Skip::Both);
        $middleware
            ->expects($this->never())
            ->method('extract');

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->addMiddleware($middleware)
            ->build();

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        self::assertEquals(
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            $hydrator->extract($event),
        );

        // the decision is cached per class, so skip is not called again
        $hydrator->extract($event);
    }

    public function testSkippableMiddlewareIsExecuted(): void
    {
        $expect = ['profileId' => '1', 'email' => 'info@patchlevel.de'];

        $middleware = $this->createMock(SkippableMiddleware::class);
        $middleware
            ->expects($this->once())
            ->method('skip')
            ->willReturn(Skip::None);
        $middleware
            ->expects($this->once())
            ->method('extract')
            ->willReturn($expect);

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->addMiddleware($middleware)
            ->build();

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        self::assertEquals($expect, $hydrator->extract($event));
    }

    public function testSkippableMiddlewareIsOnlySkippedWhileHydrating(): void
    {
        $data = ['profileId' => '1', 'email' => 'info@patchlevel.de'];

        $middleware = $this->createMock(SkippableMiddleware::class);
        $middleware
            ->method('skip')
            ->willReturn(Skip::Hydrate);
        $middleware
            ->expects($this->never())
            ->method('hydrate');
        $middleware
            ->expects($this->once())
            ->method('extract')
            ->willReturn($data);

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->addMiddleware($middleware)
            ->build();

        $event = $hydrator->hydrate(ProfileCreated::class, $data);

        self::assertEquals($data, $hydrator->extract($event));
    }

    public function testSkippableMiddlewareIsOnlySkippedWhileExtracting(): void
    {
        $data = ['profileId' => '1', 'email' => 'info@patchlevel.de'];

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $middleware = $this->createMock(SkippableMiddleware::class);
        $middleware
            ->method('skip')
            ->willReturn(Skip::Extract);
        $middleware
            ->expects($this->once())
            ->method('hydrate')
            ->willReturn($event);
        $middleware
            ->expects($this->never())
            ->method('extract');

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->addMiddleware($middleware)
            ->build();

        self::assertSame($event, $hydrator->hydrate(ProfileCreated::class, $data));
        self::assertEquals($data, $hydrator->extract($event));
    }

    public function testAllMiddlewaresSkipped(): void
    {
        $middleware = $this->createMock(SkippableMiddleware::class);
        $middleware
            ->method('skip')
            ->willReturn(Skip::Both);

        $hydrator = new StackHydrator(
            new AttributeMetadataFactory(),
            [$middleware],
        );

        $this->expectException(AllMiddlewaresSkipped::class);
        $this->expectExceptionMessage(
            'All middlewares were skipped for the class "' . ProfileCreated::class . '", at least one middleware must run.',
        );

        $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', 'email' => 'info@patchlevel.de']);
    }
}
