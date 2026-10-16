<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ClassTransformerFactory;
use Patchlevel\Hydrator\Transformer\TransformerResolver;

use function assert;
use function is_string;
use function strtoupper;

/** Only responsible for Skill, which it writes in upper case. */
final class UpperCaseSkillTransformerFactory implements ClassTransformerFactory
{
    public int $created = 0;

    public function create(ClassMetadata $metadata, TransformerResolver $resolver): ClassTransformer|null
    {
        if ($metadata->className !== Skill::class) {
            return null;
        }

        $this->created++;

        return new class implements ClassTransformer {
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
             * @param array<string, mixed> $context
             *
             * @return array<string, mixed>
             */
            public function extract(object $object, array $context): array
            {
                assert($object instanceof Skill);

                return ['name' => strtoupper($object->name)];
            }
        };
    }
}
