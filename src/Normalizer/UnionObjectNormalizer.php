<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use Attribute;
use Patchlevel\Hydrator\Hydrator;

use function array_flip;
use function array_key_exists;
use function array_keys;
use function implode;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class UnionObjectNormalizer implements Normalizer, HydratorAwareNormalizer
{
    private Hydrator|null $hydrator = null;

    /** @var array<string, class-string> */
    private array $typeToClassMap;

    /** @param array<class-string, string> $classToTypeMap */
    public function __construct(
        private readonly array|null $classToTypeMap = null,
        private readonly string $typeFieldName = '_type',
    ) {
        $this->typeToClassMap = array_flip($classToTypeMap);
    }

    public function setHydrator(Hydrator $hydrator): void
    {
        $this->hydrator = $hydrator;
    }

    public function normalize(mixed $value): mixed
    {
        if (!$this->hydrator) {
            throw new MissingHydrator();
        }

        if ($value === null) {
            return null;
        }

        if (!is_object($value)) {
            throw InvalidArgument::withWrongType(
                sprintf('%s|null', implode('|', array_keys($this->classToTypeMap))),
                $value,
            );
        }

        if (!array_key_exists($value::class, $this->classToTypeMap)) {
            throw InvalidArgument::withWrongType(
                sprintf('%s|null', implode('|', array_keys($this->classToTypeMap))),
                $value,
            );
        }

        $data = $this->hydrator->extract($value);
        $data[$this->typeFieldName] = $this->classToTypeMap[$value::class];

        return $data;
    }

    public function denormalize(mixed $value): mixed
    {
        if (!$this->hydrator) {
            throw new MissingHydrator();
        }

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw InvalidArgument::withWrongType('array<string, mixed>|null', $value);
        }

        if (!array_key_exists($this->typeFieldName, $value)) {
            throw new InvalidArgument(sprintf('missing type field "%s"', $this->typeFieldName));
        }

        $type = $value[$this->typeFieldName];

        if (!is_string($type)) {
            throw InvalidArgument::withWrongType('string', $type);
        }

        if (!array_key_exists($type, $this->typeToClassMap)) {
            throw new InvalidArgument(sprintf('unknown type "%s"', $type));
        }

        $className = $this->typeToClassMap[$type];
        unset($value[$this->typeFieldName]);

        return $this->hydrator->hydrate($className, $value);
    }

    /**
     * @return array{
     *     typeToClassMap: array<string, class-string>,
     *     classToTypeMap: array<class-string, string>,
     *     typeFieldName: string,
     *     hydrator: null
     * }
     */
    public function __serialize(): array
    {
        return [
            'typeToClassMap' => $this->typeToClassMap,
            'classToTypeMap' => $this->classToTypeMap,
            'typeFieldName' => $this->typeFieldName,
            'hydrator' => null,
        ];
    }
}
