<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Generated;

use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerExtension;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerFactory;
use Patchlevel\Hydrator\Extension\Generated\GeneratedTransformerWarmer;
use Patchlevel\Hydrator\Extension\Generated\TransformerFiles;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ReflectionTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function glob;
use function uniqid;

#[CoversClass(GeneratedTransformerExtension::class)]
#[CoversClass(GeneratedTransformerFactory::class)]
#[CoversClass(TransformerFiles::class)]
final class GeneratedTransformerExtensionTest extends TestCase
{
    public function testRegistersTransformerFactory(): void
    {
        $builder = (new StackHydratorBuilder())->useExtension(new GeneratedTransformerExtension('/tmp'));

        self::assertCount(1, $builder->transformerFactories());
        self::assertInstanceOf(GeneratedTransformerFactory::class, $builder->transformerFactories()[0]);
    }

    public function testWarmedUpTransformerIsUsed(): void
    {
        $path = self::uniquePath();
        $field = uniqid('email_');
        $hydrator = self::hydrator($path, $field);

        (new GeneratedTransformerWarmer($hydrator, $path))->warmup([ProfileCreated::class]);

        $event = $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', $field => 'info@patchlevel.de']);

        self::assertEquals(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')), $event);
        self::assertSame(['profileId' => '1', $field => 'info@patchlevel.de'], $hydrator->extract($event));
        self::assertStringStartsWith(TransformerFiles::NAMESPACE . '\\ProfileCreatedTransformer_', self::transformer($hydrator, ProfileCreated::class)::class);
    }

    public function testNothingIsGeneratedAtRuntime(): void
    {
        $path = self::uniquePath();
        $field = uniqid('email_');
        $hydrator = self::hydrator($path, $field);

        $event = $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', $field => 'info@patchlevel.de']);
        $hydrator->extract($event);

        self::assertInstanceOf(ReflectionTransformer::class, self::transformer($hydrator, ProfileCreated::class));
        self::assertDirectoryDoesNotExist($path);
    }

    public function testChangedClassFallsBackToReflection(): void
    {
        $path = self::uniquePath();
        $field = uniqid('email_');

        (new GeneratedTransformerWarmer(self::hydrator($path, $field), $path))->warmup([ProfileCreated::class]);

        // the field was renamed after the warmup, the generated code no longer matches and is not used
        $hydrator = self::hydrator($path, $field . '_renamed');
        $event = $hydrator->hydrate(ProfileCreated::class, ['profileId' => '1', $field . '_renamed' => 'info@patchlevel.de']);

        self::assertSame('info@patchlevel.de', $event->email->toString());
        self::assertInstanceOf(ReflectionTransformer::class, self::transformer($hydrator, ProfileCreated::class));
        self::assertCount(1, glob($path . '/ProfileCreatedTransformer_*.php') ?: []);
    }

    public function testAnonymousClassesAreTransformedWithReflection(): void
    {
        $object = new class {
            public string $name = 'foo';
        };

        $hydrator = self::hydrator(self::uniquePath());

        self::assertSame(['name' => 'foo'], $hydrator->extract($object));
        self::assertInstanceOf(ReflectionTransformer::class, self::transformer($hydrator, $object::class));
    }

    /**
     * Classes are loaded once per process, renaming the email field to a unique name gives the class a fingerprint
     * no transformer was loaded for yet.
     */
    private static function hydrator(string $path, string|null $emailField = null): StackHydrator
    {
        $builder = (new StackHydratorBuilder())->useExtension(new GeneratedTransformerExtension($path));

        if ($emailField !== null) {
            $builder->addMetadataEnricher(new class ($emailField) implements MetadataEnricher {
                public function __construct(private readonly string $field)
                {
                }

                public function enrich(ClassMetadata $classMetadata): void
                {
                    $property = $classMetadata->properties['email'] ?? null;

                    if ($property === null) {
                        return;
                    }

                    $property->fieldName = $this->field;
                }
            });
        }

        return $builder->build();
    }

    /** @param class-string $class */
    private static function transformer(StackHydrator $hydrator, string $class): ClassTransformer
    {
        $transformer = (new ReflectionMethod(StackHydrator::class, 'transformer'))->invoke($hydrator, $hydrator->metadata($class));
        self::assertInstanceOf(ClassTransformer::class, $transformer);

        return $transformer;
    }

    private static function uniquePath(): string
    {
        return GeneratedStackHydratorTest::cachePath() . '/' . uniqid('cache', true);
    }
}
