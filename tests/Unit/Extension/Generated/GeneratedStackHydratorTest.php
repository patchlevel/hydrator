<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Patchlevel\Hydrator\ArrayDataRequired;
use Patchlevel\Hydrator\CircularReference;
use Patchlevel\Hydrator\ClassNotSupported;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Generated\ClassPlan;
use Patchlevel\Hydrator\Extension\Generated\ClassPlanner;
use Patchlevel\Hydrator\Extension\Generated\CodeEmitter;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapper;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperFactory;
use Patchlevel\Hydrator\Extension\Generated\GeneratedMapperWarmer;
use Patchlevel\Hydrator\Extension\Generated\MapperFiles;
use Patchlevel\Hydrator\Extension\Generated\MapperGenerator;
use Patchlevel\Hydrator\Extension\Generated\PropertyPlan;
use Patchlevel\Hydrator\Extension\Generated\Templates;
use Patchlevel\Hydrator\Extension\Generated\ValueKind;
use Patchlevel\Hydrator\Extension\Lifecycle\LifecycleExtension;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\Upcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\Mapper\LazyMapper;
use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Mapper\ReflectionMapper;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\MiddlewareMapper;
use Patchlevel\Hydrator\Middleware\Skip;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture\SensitiveDataProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\AliasDto;
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
use ReflectionProperty;

use function array_filter;
use function array_values;
use function get_object_vars;
use function glob;
use function preg_match;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const ARRAY_FILTER_USE_KEY;
use const PHP_VERSION_ID;

/**
 * Mirrors the StackHydratorTest: the generated mappers must behave exactly like the ReflectionMapper.
 */
#[CoversClass(GeneratedMapperExtension::class)]
#[CoversClass(GeneratedMapperFactory::class)]
#[CoversClass(MapperGenerator::class)]
#[CoversClass(GeneratedMapperWarmer::class)]
#[CoversClass(GeneratedMapper::class)]
#[CoversClass(MapperFiles::class)]
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
        AliasDto::class,
    ];

    private static string $cachePath;

    private StackHydrator $hydrator;

    public static function setUpBeforeClass(): void
    {
        self::$cachePath = sys_get_temp_dir() . '/' . uniqid('patchlevel-hydrator-tests-', true);

        $classes = self::supportedClasses();

        if (PHP_VERSION_ID >= 80400) {
            $classes[] = AsymmetricVisibilityDto::class;
        }

        (new GeneratedMapperWarmer((new StackHydratorBuilder())->metadataFactory(), self::$cachePath))->warmup($classes);
    }

    public static function tearDownAfterClass(): void
    {
        foreach (glob(self::$cachePath . '/*.php') ?: [] as $file) {
            unlink($file);
        }

        rmdir(self::$cachePath);
    }

    public function setUp(): void
    {
        $this->hydrator = $this->builder()->build();
    }

    /**
     * Closures in attributes (used by the inline normalizer fixture) are only supported since PHP 8.5.
     *
     * @return list<class-string>
     */
    private static function supportedClasses(): array
    {
        if (PHP_VERSION_ID >= 80500) {
            return self::CLASSES;
        }

        return array_values(array_filter(
            self::CLASSES,
            static fn (string $class): bool => $class !== ProfileCreatedWithInlineNormalizer::class,
        ));
    }

    private function builder(): StackHydratorBuilder
    {
        return (new StackHydratorBuilder())->useExtension($this->extension());
    }

    private function extension(): GeneratedMapperExtension
    {
        return new GeneratedMapperExtension(self::$cachePath);
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
                $this->callback(static fn (array $context): bool => $context['context'] === '123'
                    && $context[Hydrator::HYDRATOR] instanceof StackHydrator),
                $this->isInstanceOf(Mapper::class),
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

    public function testHydrateWithAlias(): void
    {
        $object = $this->hydrator->hydrate(AliasDto::class, ['mail' => 'info@patchlevel.de', 'username' => 'patchlevel']);

        self::assertEquals(new AliasDto(Email::fromString('info@patchlevel.de'), 'patchlevel'), $object);
    }

    public function testHydrateWithFurtherAlias(): void
    {
        $object = $this->hydrator->hydrate(AliasDto::class, ['e_mail' => 'info@patchlevel.de']);

        self::assertEquals(new AliasDto(Email::fromString('info@patchlevel.de')), $object);
    }

    public function testHydrateFieldIsPreferredOverAlias(): void
    {
        $object = $this->hydrator->hydrate(
            AliasDto::class,
            ['mail' => 'old@patchlevel.de', 'email' => 'new@patchlevel.de', 'username' => 'old', 'name' => 'new'],
        );

        self::assertEquals(new AliasDto(Email::fromString('new@patchlevel.de'), 'new'), $object);
    }

    public function testHydrateFirstAliasIsPreferred(): void
    {
        $object = $this->hydrator->hydrate(AliasDto::class, ['e_mail' => 'second@patchlevel.de', 'mail' => 'first@patchlevel.de']);

        self::assertEquals(new AliasDto(Email::fromString('first@patchlevel.de')), $object);
    }

    public function testExtractWithAlias(): void
    {
        self::assertSame(
            ['email' => 'info@patchlevel.de', 'name' => 'patchlevel'],
            $this->hydrator->extract(new AliasDto(Email::fromString('info@patchlevel.de'), 'patchlevel')),
        );
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
                $this->callback(static fn (array $context): bool => $context['context'] === '123'
                    && $context[Hydrator::HYDRATOR] instanceof StackHydrator),
                $this->isInstanceOf(Mapper::class),
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
        $hydrator = $this->builder()->build();

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

    public function testChangedClassFallsBackToReflection(): void
    {
        // the renamed fields change the fingerprint, like a class which changed after the warmup
        $hydrator = $this->builder()
            ->addMetadataEnricher(new class implements MetadataEnricher {
                public function enrich(ClassMetadata $classMetadata): void
                {
                    foreach ($classMetadata->properties as $property) {
                        $property->fieldName = 'renamed_' . $property->fieldName;
                    }
                }
            })
            ->build();

        $data = ['renamed_profileId' => '1', 'renamed_email' => 'info@patchlevel.de'];
        $event = $hydrator->hydrate(ProfileCreated::class, $data);

        self::assertEquals(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')), $event);
        self::assertSame($data, $hydrator->extract($event));
        self::assertInstanceOf(ReflectionMapper::class, self::mappers($hydrator)[ProfileCreated::class]);
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
     * Reads the inlining decisions of the generated mappers the hydrator uses.
     *
     * @return array<string, bool> flag name => mapped in place
     */
    private static function inlined(StackHydrator $hydrator, string $class): array
    {
        /** @var array<string, bool> $flags */
        $flags = array_filter(
            get_object_vars(self::generated($hydrator, $class)),
            static fn (string $name): bool => preg_match('/^i\d+$/', $name) === 1,
            ARRAY_FILTER_USE_KEY,
        );

        return $flags;
    }

    /** The generated mapper of the class, unwrapped from the middlewares and the lazy proxy. */
    private static function generated(StackHydrator $hydrator, string $class): GeneratedMapper
    {
        $mapper = self::mappers($hydrator)[$class] ?? null;

        while ($mapper instanceof MiddlewareMapper || $mapper instanceof LazyMapper) {
            $mapper = (new ReflectionProperty($mapper, $mapper instanceof LazyMapper ? 'mapper' : 'next'))->getValue($mapper);
        }

        self::assertInstanceOf(GeneratedMapper::class, $mapper);

        return $mapper;
    }

    /** @return array<class-string, Mapper<object>> */
    private static function mappers(StackHydrator $hydrator): array
    {
        /** @var array<class-string, Mapper<object>> $mappers */
        $mappers = (new ReflectionProperty(StackHydrator::class, 'mappers'))->getValue($hydrator);

        return $mappers;
    }

    public function testGeneratedMappersAreUsed(): void
    {
        $data = ['profileId' => '1', 'email' => 'info@patchlevel.de'];
        $this->hydrator->extract($this->hydrator->hydrate(ProfileCreated::class, $data));

        $mapper = self::mappers($this->hydrator)[ProfileCreated::class];

        self::assertInstanceOf(GeneratedMapper::class, $mapper);
        self::assertStringStartsWith(MapperFiles::NAMESPACE . '\\', $mapper::class);
    }

    public function testNestedObjectsAreInlinedWithoutOtherMiddlewares(): void
    {
        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]];

        self::assertSame($data, $this->hydrator->extract($this->hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertSame(
            ['i0' => true, 'i1' => true],
            self::inlined($this->hydrator, NestedLifecycleDto::class),
        );
    }

    public function testNestedObjectsAreInlinedWhenOtherMiddlewaresSkipThem(): void
    {
        $counter = new CountingMiddleware([LifecycleFixture::class => Skip::Both]);

        $hydrator = $this->builder()
            ->addMiddleware($counter)
            ->build();

        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]];
        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));

        self::assertSame(
            ['i0' => true, 'i1' => true],
            self::inlined($hydrator, NestedLifecycleDto::class),
        );
        self::assertSame([NestedLifecycleDto::class => 1], $counter->hydrated);
    }

    public function testNestedObjectsAreMappedWithTheirChainWhenAMiddlewareRunsForThem(): void
    {
        // an upcaster which can not be introspected runs for every class while hydrating
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

        $hydrator = $this->builder()
            ->useExtension(new UpcastExtension([$upcaster]))
            ->build();

        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]];
        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertSame(['i0' => true, 'i1' => true], self::inlined($hydrator, NestedLifecycleDto::class));

        // callback upcasters for other classes do not prevent inlining
        $hydrator = $this->builder()
            ->useExtension(new UpcastExtension([CallbackUpcaster::forClass(ProfileCreated::class, static fn (array $data): array => $data)]))
            ->build();

        self::assertSame($data, $hydrator->extract($hydrator->hydrate(NestedLifecycleDto::class, $data)));
        self::assertSame(
            ['i0' => true, 'i1' => true],
            self::inlined($hydrator, NestedLifecycleDto::class),
        );
    }

    public function testGeneratedMapperRunsAfterOtherMiddlewares(): void
    {
        $counter = new CountingMiddleware();

        $hydrator = $this->builder()
            ->addMiddleware($counter)
            ->build();

        $data = ['profileId' => '1', 'email' => 'info@patchlevel.de'];
        self::assertSame($data, $hydrator->extract($hydrator->hydrate(ProfileCreated::class, $data)));

        self::assertInstanceOf(MiddlewareMapper::class, self::mappers($hydrator)[ProfileCreated::class]);
        self::generated($hydrator, ProfileCreated::class);
        self::assertSame([ProfileCreated::class => 1], $counter->hydrated);
        self::assertSame([ProfileCreated::class => 1], $counter->extracted);
    }

    public function testNestedObjectsGoThroughTheMiddlewaresOfTheirClass(): void
    {
        $counter = new CountingMiddleware();

        $hydrator = $this->builder()
            ->addMiddleware($counter)
            ->build();

        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b'], ['name' => 'c']]];

        // the chain of the nested class contains the middleware, it sees every nested object
        $object = $hydrator->hydrate(NestedLifecycleDto::class, $data);
        self::assertSame($data, $hydrator->extract($object));

        self::assertSame([NestedLifecycleDto::class => 1, LifecycleFixture::class => 3], $counter->hydrated);
        self::assertSame([NestedLifecycleDto::class => 1, LifecycleFixture::class => 3], $counter->extracted);
        self::assertSame(['i0' => true, 'i1' => true], self::inlined($hydrator, NestedLifecycleDto::class));
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

    public function testOuterHydratorIsUsedForNestedObjects(): void
    {
        $outer = new class ($this->hydrator) implements Hydrator {
            /** @var list<string> */
            public array $calls = [];

            public function __construct(private readonly Hydrator $inner)
            {
            }

            /**
             * @param class-string<T>      $class
             * @param array<string, mixed> $context
             *
             * @return T
             *
             * @template T of object
             */
            public function hydrate(string $class, mixed $data, array $context = []): object
            {
                $context[Hydrator::HYDRATOR] ??= $this;
                $this->calls[] = 'hydrate ' . $class;

                return $this->inner->hydrate($class, $data, $context);
            }

            /** @param array<string, mixed> $context */
            public function extract(object $object, array $context = []): mixed
            {
                $context[Hydrator::HYDRATOR] ??= $this;
                $this->calls[] = 'extract ' . $object::class;

                return $this->inner->extract($object, $context);
            }
        };

        $data = ['child' => ['name' => 'a'], 'items' => [['name' => 'b']]];

        // nested objects are mapped in place for calls of the hydrator itself
        self::assertSame($data, $this->hydrator->extract($this->hydrator->hydrate(NestedLifecycleDto::class, $data)));

        // but go through the outer hydrator for its calls
        self::assertSame($data, $outer->extract($outer->hydrate(NestedLifecycleDto::class, $data)));
        self::assertSame(
            [
                'hydrate ' . NestedLifecycleDto::class,
                'hydrate ' . LifecycleFixture::class,
                'hydrate ' . LifecycleFixture::class,
                'extract ' . NestedLifecycleDto::class,
                'extract ' . LifecycleFixture::class,
                'extract ' . LifecycleFixture::class,
            ],
            $outer->calls,
        );
    }
}
