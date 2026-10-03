<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Patchlevel\Hydrator\ArrayDataRequired;
use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\ClassNotSupported;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Generated\ClassPlan;
use Patchlevel\Hydrator\Extension\Generated\ClassPlanner;
use Patchlevel\Hydrator\Extension\Generated\CodeEmitter;
use Patchlevel\Hydrator\Extension\Generated\GeneratedHydrator;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddleware;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMiddlewareExtension;
use Patchlevel\Hydrator\Extension\Generated\MiddlewareGenerator;
use Patchlevel\Hydrator\Extension\Generated\PropertyPlan;
use Patchlevel\Hydrator\Extension\Generated\Templates;
use Patchlevel\Hydrator\Extension\Generated\ValueKind;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\Middleware\SkippableMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture\SensitiveDataProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\AsymmetricVisibilityDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle1Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle2Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Circle3Dto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ContextAwareDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\CountingMiddleware;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DefaultDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerWithIterablesDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\InferNormalizerWithNullableDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LazyProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\LifecycleFixture;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NestedLifecycleDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\NormalizerInBaseClassDefinedDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\PrivateChildDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\PrivatePropertiesDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWithInlineNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWithNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWrapper;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Skill;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use Patchlevel\Hydrator\Tests\Unit\Fixture\StatusWithNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ValueObject;
use Patchlevel\Hydrator\Tests\Unit\Fixture\WrongNormalizer;
use Patchlevel\Hydrator\TypeMismatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_filter;
use function get_object_vars;
use function preg_match;
use function str_starts_with;
use function sys_get_temp_dir;

use const ARRAY_FILTER_USE_KEY;

/**
 * Mirrors the StackHydratorTest: the generated middleware must behave exactly like the TransformMiddleware.
 */
#[CoversClass(GeneratedMiddlewareExtension::class)]
#[CoversClass(MiddlewareGenerator::class)]
#[CoversClass(ClassPlanner::class)]
#[CoversClass(ClassPlan::class)]
#[CoversClass(PropertyPlan::class)]
#[CoversClass(ValueKind::class)]
#[CoversClass(CodeEmitter::class)]
#[CoversClass(Templates::class)]
final class GeneratedStackHydratorTest extends TestCase
{
    public const CLASSES = [
        ProfileCreated::class,
        ParentDto::class,
        ProfileCreatedWrapper::class,
        Circle1Dto::class,
        Circle2Dto::class,
        Circle3Dto::class,
        ContextAwareDto::class,
        DefaultDto::class,
        InferNormalizerDto::class,
        InferNormalizerWithNullableDto::class,
        InferNormalizerWithIterablesDto::class,
        NormalizerInBaseClassDefinedDto::class,
        LazyProfileCreated::class,
        WrongNormalizer::class,
        ProfileCreatedWithInlineNormalizer::class,
        PrivatePropertiesDto::class,
        PrivateChildDto::class,
        SensitiveDataProfileCreated::class,
        NestedLifecycleDto::class,
    ];

    private StackHydrator $hydrator;

    public function setUp(): void
    {
        $this->hydrator = $this->builder()->build();
    }

    /** @param list<class-string> $classes */
    private function builder(array $classes = self::CLASSES): StackHydratorBuilder
    {
        return (new StackHydratorBuilder())->useExtension(new CoreExtension())->useExtension($this->extension($classes));
    }

    /** @param list<class-string> $classes */
    private function extension(array $classes = self::CLASSES): GeneratedMiddlewareExtension
    {
        return new GeneratedMiddlewareExtension(
            self::cachePath(),
            $classes,
            debug: true,
        );
    }

    public static function cachePath(): string
    {
        return sys_get_temp_dir() . '/patchlevel-hydrator-tests';
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

        $this->hydrator->extract($dto1);
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

        $hydrator = $this->builder()
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

    public function testExtractPrivateProperties(): void
    {
        $dto = new PrivatePropertiesDto(ProfileId::fromString('1'), 'foo', 12);

        self::assertSame(
            ['profileId' => '1', 'name' => 'foo', 'age' => 12],
            $this->hydrator->extract($dto),
        );
    }

    public function testExtractPrivatePropertiesOfParent(): void
    {
        $dto = new PrivateChildDto(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'), 'note', 'foo');

        self::assertSame(
            ['profileId' => '1', 'name' => 'foo', 'email' => 'info@patchlevel.de', 'note' => 'note'],
            $this->hydrator->extract($dto),
        );
        self::assertSame((new StackHydrator())->extract($dto), $this->hydrator->extract($dto));
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
            // @phpstan-ignore argument.type
            'Unknown',
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );
    }

    public function testHydrateWithArrayDataRequired(): void
    {
        $this->expectException(ArrayDataRequired::class);

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

    public function testHydrateWithTypeMismatch(): void
    {
        $this->expectException(TypeMismatch::class);
        $this->expectExceptionMessage('The value could not be set because the expected type of the property "profileId" in class "Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated" does not match.');

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

        $hydrator = $this->builder()
            ->addMiddleware($middleware)
            ->build();

        $object = $hydrator->hydrate(InferNormalizerDto::class, $data, ['context' => '123']);

        self::assertEquals($expect, $object);
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

    public function testHydrateWithClassNormalizer(): void
    {
        $object = $this->hydrator->hydrate(
            ProfileId::class,
            'id',
        );

        self::assertEquals(ProfileId::fromString('id'), $object);
    }

    public function testHydratePrivateProperties(): void
    {
        $dto = $this->hydrator->hydrate(
            PrivatePropertiesDto::class,
            ['profileId' => '1', 'name' => 'foo', 'age' => 12],
        );

        self::assertEquals(ProfileId::fromString('1'), $dto->profileId());
        self::assertSame('foo', $dto->name());
        self::assertSame(12, $dto->age());
    }

    public function testHydratePrivatePropertiesOfParent(): void
    {
        $dto = $this->hydrator->hydrate(
            PrivateChildDto::class,
            ['email' => 'info@patchlevel.de', 'note' => 'note', 'profileId' => '1', 'name' => 'foo'],
        );

        self::assertEquals(ProfileId::fromString('1'), $dto->profileId);
        self::assertEquals(Email::fromString('info@patchlevel.de'), $dto->email());
        self::assertSame('note', $dto->note());
        self::assertSame('foo', $dto->name());
    }

    #[RequiresPhp('>=8.4')]
    public function testHydrateAsymmetricVisibility(): void
    {
        $data = ['name' => 'foo', 'age' => 12, 'note' => 'note'];
        $hydrator = $this->builder([AsymmetricVisibilityDto::class])->build();

        $dto = $hydrator->hydrate(AsymmetricVisibilityDto::class, $data);

        self::assertEquals(new AsymmetricVisibilityDto('foo', 12, 'note'), $dto);
        self::assertSame($data, $hydrator->extract($dto));
    }

    public function testHydrateCoercesScalarsLikeReflection(): void
    {
        $dto = $this->hydrator->hydrate(
            PrivatePropertiesDto::class,
            ['profileId' => '1', 'name' => 'foo', 'age' => '12'],
        );

        self::assertSame(12, $dto->age());
    }

    public function testHydrateUnknownClassFallsBackToTransformMiddleware(): void
    {
        $hydrator = $this->builder([ProfileCreated::class])->build();

        $event = $hydrator->hydrate(
            ParentDto::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
        );

        self::assertEquals(new ParentDto(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')), $event);
        self::assertEquals(['profileId' => '1', 'email' => 'info@patchlevel.de'], $hydrator->extract($event));
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

    public function testDecrypt(): void
    {
        $object = new SensitiveDataProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $encryptedPayload = ['id' => '1', 'email' => 'encrypted'];

        $cryptographer = $this->createMock(Cryptographer::class);
        $cryptographer
            ->expects($this->once())
            ->method('supports')
            ->with('encrypted')
            ->willReturn(true);

        $cryptographer
            ->expects($this->once())
            ->method('decrypt')
            ->with('1', 'encrypted')
            ->willReturn('info@patchlevel.de');

        $hydrator = $this->builder()
            ->useExtension(new CryptographyExtension($cryptographer))
            ->build();

        $return = $hydrator->hydrate(SensitiveDataProfileCreated::class, $encryptedPayload);

        self::assertEquals($object, $return);
    }

    public function testEncrypt(): void
    {
        $object = new SensitiveDataProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $encryptedPayload = [
            'id' => '1',
            'email' => [
                '__enc' => 'v1',
                'data' => 'encrypted',
                'method' => 'foo',
                'iv' => 'bar',
            ],
        ];

        $cryptographer = $this->createMock(Cryptographer::class);

        $cryptographer
            ->expects($this->never())
            ->method('supports');

        $cryptographer
            ->expects($this->once())
            ->method('encrypt')
            ->with('1', 'info@patchlevel.de')
            ->willReturn([
                '__enc' => 'v1',
                'data' => 'encrypted',
                'method' => 'foo',
                'iv' => 'bar',
            ]);

        $hydrator = $this->builder()
            ->useExtension(new CryptographyExtension($cryptographer))
            ->build();

        $return = $hydrator->extract($object);

        self::assertSame($encryptedPayload, $return);
    }

    /**
     * Reads the inlining decisions of the generated middleware.
     *
     * @return array<string, bool> flag name => inlined
     */
    private static function inlined(StackHydrator $hydrator): array
    {
        foreach ($hydrator->middlewares() as $middleware) {
            if (str_starts_with($middleware::class, GeneratedMiddlewareExtension::NAMESPACE . '\\')) {
                /** @var array<string, bool> $flags */
                $flags = array_filter(
                    get_object_vars($middleware),
                    static fn (string $name): bool => preg_match('/^i[he]\d+$/', $name) === 1,
                    ARRAY_FILTER_USE_KEY,
                );

                return $flags;
            }
        }

        self::fail('generated middleware not found');
    }

    public function testNestedObjectsAreInlinedWithoutOtherMiddlewares(): void
    {
        $hydrator = $this->builder([NestedLifecycleDto::class])->build();
        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]];

        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertSame(['ih0' => true, 'ie0' => true, 'ih1' => true, 'ie1' => true], self::inlined($hydrator));
    }

    public function testNestedObjectsAreInlinedWhenOtherMiddlewaresSkipThem(): void
    {
        $counter = new CountingMiddleware([LifecycleFixture::class => Skip::Both]);

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension($this->extension([NestedLifecycleDto::class]))
            ->addMiddleware($counter)
            ->build();

        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]];
        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));

        self::assertSame(['ih0' => true, 'ie0' => true, 'ih1' => true, 'ie1' => true], self::inlined($hydrator));
        self::assertSame([NestedLifecycleDto::class => 1], $counter->hydrated);
    }

    public function testInliningIsDecidedPerDirection(): void
    {
        // an upcaster which can not be introspected keeps the upcast middleware in the hydrate stack
        $upcaster = new class implements Upcaster {
            /**
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function upcast(ClassMetadata $metadata, array $data, array $context): array
            {
                return $data;
            }
        };

        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension($this->extension([NestedLifecycleDto::class]))
            ->useExtension(new UpcastExtension([$upcaster]))
            ->build();

        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]];
        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertSame(['ih0' => false, 'ie0' => true, 'ih1' => false, 'ie1' => true], self::inlined($hydrator));

        // callback upcasters for other classes do not prevent inlining
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension($this->extension([NestedLifecycleDto::class]))
            ->useExtension(new UpcastExtension([CallbackUpcaster::forClass(ProfileCreated::class, static fn (array $data): array => $data)]))
            ->build();

        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertSame(['ih0' => true, 'ie0' => true, 'ih1' => true, 'ie1' => true], self::inlined($hydrator));
    }

    public function testCompiledClosures(): void
    {
        $hydrator = $this->builder([ProfileCreated::class])->build();
        $middleware = $hydrator->middlewares()[0];

        self::assertInstanceOf(GeneratedHydrator::class, $hydrator);
        self::assertInstanceOf(GeneratedMiddleware::class, $middleware);
        self::assertNotNull($middleware->compiledHydrator($hydrator->metadata(ProfileCreated::class)));
        self::assertNotNull($middleware->compiledExtractor($hydrator->metadata(ProfileCreated::class)));
        self::assertNull($middleware->compiledHydrator($hydrator->metadata(ParentDto::class)));
        self::assertNull($middleware->compiledExtractor($hydrator->metadata(ParentDto::class)));

        // with a middleware which does not skip the class, the stack has to run
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension($this->extension([ProfileCreated::class]))
            ->addMiddleware(new CountingMiddleware())
            ->build();
        $middleware = $hydrator->middlewares()[1];

        self::assertInstanceOf(GeneratedMiddleware::class, $middleware);
        self::assertNull($middleware->compiledHydrator($hydrator->metadata(ProfileCreated::class)));
        self::assertNull($middleware->compiledExtractor($hydrator->metadata(ProfileCreated::class)));
    }

    public function testUnknownClassesAreSkipped(): void
    {
        $hydrator = $this->builder([ProfileCreated::class])->build();
        $middleware = $hydrator->middlewares()[0];

        self::assertInstanceOf(SkippableMiddleware::class, $middleware);
        self::assertSame(Skip::None, $middleware->skip($hydrator->metadata(ProfileCreated::class)));
        self::assertSame(Skip::Both, $middleware->skip($hydrator->metadata(ParentDto::class)));
    }

    public function testNestedObjectsAreNotInlinedWithNonSkippableMiddleware(): void
    {
        $counter = new CountingMiddleware();

        $hydrator = $this->builder()->build();
        $direct = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension($this->extension())
            ->addMiddleware($counter)
            ->build();

        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b'], ['name' => 'c']]];

        $object = $hydrator->hydrate(NestedLifecycleDto::class, $data);
        self::assertSame($data, $hydrator->extract($object));

        // with another middleware in the stack, nested objects go through the whole stack
        $object = $direct->hydrate(NestedLifecycleDto::class, $data);
        self::assertSame($data, $direct->extract($object));

        self::assertSame([NestedLifecycleDto::class => 1, LifecycleFixture::class => 3], $counter->hydrated);
        self::assertSame([NestedLifecycleDto::class => 1, LifecycleFixture::class => 3], $counter->extracted);
        self::assertNotContains(true, self::inlined($direct));
    }

    public function testNestedObjectsRespectOtherMiddlewares(): void
    {
        $hydrator = $this->builder()
            ->useExtension(new LifecycleExtension())
            ->build();

        $object = $hydrator->hydrate(
            NestedLifecycleDto::class,
            ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]],
        );

        self::assertSame('a [preHydrate] [postHydrate]', $object->child->name);
        self::assertSame('b [preHydrate] [postHydrate]', $object->items[0]->name);

        self::assertSame(
            [
                'child' => ['name' => 'a [preHydrate] [postHydrate] [preExtract] [postExtract]'],
                'items' => [['name' => 'b [preHydrate] [postHydrate] [preExtract] [postExtract]']],
            ],
            $hydrator->extract($object),
        );
    }
}
