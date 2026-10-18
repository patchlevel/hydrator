<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Mapper\Mapper;
use Patchlevel\Hydrator\Mapper\MapperFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;

use function assert;
use function is_string;
use function strtoupper;

/** Only responsible for Skill, which it writes in upper case. */
final class UpperCaseSkillMapperFactory implements MapperFactory
{
    public int $created = 0;

    public StackHydrator|null $hydrator = null;

    /**
     * @param ClassMetadata<T> $metadata
     *
     * @return Mapper<T>|null
     *
     * @template T of object
     */
    public function create(ClassMetadata $metadata, StackHydrator $hydrator): Mapper|null
    {
        if ($metadata->className !== Skill::class) {
            return null;
        }

        $this->created++;
        $this->hydrator = $hydrator;

        /** @var Mapper<T> $mapper */
        $mapper = self::mapper();

        return $mapper;
    }

    /** @return Mapper<Skill> */
    private static function mapper(): Mapper
    {
        return new /** @implements Mapper<Skill> */ class implements Mapper {
            /**
             * @param array<string, mixed> $data
             * @param array<string, mixed> $context
             */
            public function hydrate(array $data, array $context): Skill
            {
                assert(is_string($data['name']));

                return new Skill(strtoupper($data['name']));
            }

            /**
             * @param Skill                $object
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(object $object, array $context): array
            {
                return ['name' => strtoupper($object->name)];
            }
        };
    }
}
