<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use ReflectionProperty;
use Throwable;
use function implode;
use function ltrim;
use function Psl\Str\pad_left;
use function str_replace;

final class MiddlewareGenerator
{
    public function __construct(
        private readonly MetadataFactory $metadataFactory,
    ) {
    }

    /**
     * @param list<class-string> $classes
     */
    public function dump(array $classes, string $middlewareFqcn): string
    {
        $parts = explode('\\', $middlewareFqcn);
        $middlewareClassName = array_pop($parts);
        $namespace = implode('\\', $parts);

        /** @var array<class-string, ClassMetadata> $allClasses */
        $allClasses = [];
        $todo = $classes;
        
        // Phase 0: Collect all recursive classes
        while ($todo !== []) {
            $class = ltrim(array_shift($todo), '\\');
            if (isset($allClasses[$class])) {
                continue;
            }
            try {
                $metadata = $this->metadataFactory->metadata($class);
                $allClasses[$class] = $metadata;

                foreach ($metadata->properties as $property) {
                    if ($property->normalizer instanceof ObjectNormalizer) {
                        $todo[] = $property->normalizer->getClassName();
                    } elseif ($property->normalizer instanceof ArrayNormalizer) {
                        $reflection = new ReflectionProperty($property->normalizer, 'normalizer');
                        $inner = $reflection->getValue($property->normalizer);
                        if ($inner instanceof ObjectNormalizer) {
                            $todo[] = $inner->getClassName();
                        }
                    }
                }
            } catch (Throwable) {
                // Skip if metadata not found
            }
        }

        $normalizers = [];
        $normalizerMap = []; // [class][fieldName] => globalIndex

        // Phase 1: Collect all normalizers
        foreach ($allClasses as $class => $metadata) {

            foreach ($metadata->properties as $property) {
                if ($property->normalizer && !$property->normalizer instanceof ObjectNormalizer) {
                    if ($property->normalizer instanceof ArrayNormalizer) {
                        $reflection = new ReflectionProperty($property->normalizer, 'normalizer');
                        $inner = $reflection->getValue($property->normalizer);
                        if ($inner instanceof ObjectNormalizer) {
                            continue; // We inline these
                        }
                    }

                    // Map normalizers by the declaring class of the property to support inheritance
                    $declaringClass = $property->reflection->getDeclaringClass()->getName();

                    $normalizers[] = [
                        'class' => $declaringClass,
                        'normalizer' => $property->normalizer::class,
                        'fieldName' => $property->fieldName,
                        'propertyName' => $property->propertyName,
                    ];
                    $normalizerMap[$declaringClass][$property->fieldName] = count($normalizers) - 1;
                }
            }
        }

        // Phase 2: Generate Properties and Setup
        $propertiesCode = '';
        $setupCode = '';

        foreach ($normalizers as $index => $info) {
            $propertiesCode .= "private readonly \\{$info['normalizer']} \$n$index;\n";
            $setupCode .= "\$this->n$index = \$metadataFactory->metadata(\\{$info['class']}::class)->properties['{$info['propertyName']}']->normalizer;\n";
        }

        // Phase 3: Generate Class Methods
        $methods = '';
        $hydrateCases = '';
        $extractCases = '';

        foreach ($allClasses as $class => $metadata) {
            $shortName = str_replace('\\', '', $class);
            
            $hydrateCases .= "\\$class::class => \$this->hydrate$shortName(\$data, \$context, \$stack),\n";
            $extractCases .= "\\$class::class => \$this->extract$shortName(\$object, \$context, \$stack),\n";
            
            $methods .= $this->generateClassMethods($metadata, $shortName, $normalizerMap);
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\Stack;
use Patchlevel\Hydrator\TypeMismatch;
use Patchlevel\Hydrator\DenormalizationFailure;
use Patchlevel\Hydrator\NormalizationFailure;
use Patchlevel\Hydrator\CircularReference;
use Throwable;
use TypeError;

use function array_key_exists;
use function spl_object_id;
use function array_values;
use function array_map;
use function ltrim;

final class $middlewareClassName implements Middleware
{
    private array \$callStack = [];

{$this->padLeft($propertiesCode, 1)}

    public function __construct(MetadataFactory \$metadataFactory)
    {
{$this->padLeft($setupCode, 2)}    
    }

    public function hydrate(ClassMetadata \$metadata, array \$data, array \$context, Stack \$stack): object
    {
        \$object = \$this->doHydrate(\$metadata->className, \$data, \$context, \$stack);
        
        if (\$object === null) {
            return \$stack->next()->hydrate(\$metadata, \$data, \$context, \$stack);
        }
    
        return \$object;
    }

    private function doHydrate(string \$class, array \$data, array \$context, Stack \$stack): object|null
    {
        return match (\$class) {
{$this->padLeft($hydrateCases, 3)}    
            default => null,
        };
    }

    public function extract(ClassMetadata \$metadata, object \$object, array \$context, Stack \$stack): array
    {
        \$data = \$this->doExtract(\$object, \$context, \$stack);
        
        if (\$data === null) {
            return \$stack->next()->extract(\$metadata, \$object, \$context, \$stack);
        }
    
        return \$data;
    }

    private function doExtract(object \$object, array \$context, Stack \$stack): array|null
    {
        \$objectId = spl_object_id(\$object);

        if (array_key_exists(\$objectId, \$this->callStack)) {
            \$references = array_values(\$this->callStack);
            \$references[] = \$object::class;

            throw new CircularReference(\$references);
        }

        \$this->callStack[\$objectId] = \$object::class;

        try {
            return match (\$object::class) {
{$this->padLeft($extractCases, 4)}
                default => null,
            };
        } finally {
            \\array_pop(\$this->callStack);
        }
    }

{$this->padLeft($methods, 1)}
}
PHP;
    }

    private function generateClassMethods(ClassMetadata $metadata, string $shortName, array $normalizerMap): string
    {
        $targetClass = $metadata->className;

        $constructor = $metadata->reflection->getConstructor();

        if ($constructor === null) {
            dd($metadata->className);
        }

        $befores = [];
        $map = [];

        foreach ($constructor->getParameters() as $parameter) {
            $tupple = $this->generatePropertyDenormalization($metadata->properties[$parameter->getName()], $normalizerMap);

            $map[] = $tupple[0];

            if ($tupple[1] !== '') {
                $befores[] = $tupple[1];
            }
        }

        $methods = <<<PHP
private function hydrate$shortName(array \$data, array \$context, Stack \$stack): \\$targetClass
{
{$this->padLeft(implode("\n", $befores), 1)}        
    return new \\$targetClass(
{$this->padLeft(implode(",\n", $map), 2)}  
    );
}

PHP;

        $befores = [];
        $map = [];

        foreach ($metadata->properties as $property) {
            $tupple = $this->generatePropertyNormalization($property, $normalizerMap);

            $map[] = $tupple[0];

            if ($tupple[1] !== '') {
                $befores[] = $tupple[1];
            }
        }

        $methods .= <<<PHP
private function extract$shortName(\$object, array \$context, Stack \$stack): array
{
{$this->padLeft(implode("\n", $befores), 1)}    
    return [
{$this->padLeft(implode("\n", $map), 2)}     
    ];
}

PHP;

        return $methods;
    }

    /**
     * @return array{string, string}
     */
    private function generatePropertyDenormalization(PropertyMetadata $property, array $normalizerMap): array
    {
        $fieldName = $property->fieldName;
        $propertyName = $property->propertyName;
        $class = $property->reflection->getDeclaringClass()->getName();
        $globalIndex = $normalizerMap[$class][$fieldName] ?? null;

        $before = '';

        if ($property->normalizer !== null) {
            if ($property->normalizer instanceof ObjectNormalizer) {
                $nestedClass = $property->normalizer->getClassName();
                $valueCode = "\$this->doHydrate(\\$nestedClass::class, \$data['$fieldName'], \$context, \$stack)";
            } elseif ($property->normalizer instanceof ArrayNormalizer) {
                $reflection = new ReflectionProperty($property->normalizer, 'normalizer');
                $inner = $reflection->getValue($property->normalizer);
                if ($inner instanceof ObjectNormalizer) {
                    $nestedClass = $inner->getClassName();
                    $before = <<<PHP
foreach (\$data['$fieldName'] as &\${$propertyName}Item) {
    \${$propertyName}Item = \$this->doHydrate(\\$nestedClass::class, \${$propertyName}Item, \$context, \$stack);
}
PHP;
                    $valueCode = "\$data['$fieldName']";
                } else {
                    $valueCode = "\$this->n{$globalIndex}->denormalize(\$data['$fieldName'], \$context)";
                }
            } elseif ($globalIndex !== null) {
                $valueCode = "\$this->n{$globalIndex}->denormalize(\$data['$fieldName'], \$context)";
            } else {
                $valueCode = "\$data['$fieldName']";
            }
        } else {
            $valueCode = "\$data['$fieldName']";
        }

        if ($property->reflection->getType()?->allowsNull()) {
            $valueCode = "\\array_key_exists('$fieldName', \$data) ? $valueCode : null";
        }

        return [$valueCode, $before];
    }

    /**
     * @return array{string, string}
     */
    private function generatePropertyNormalization(PropertyMetadata $property, array $normalizerMap): array
    {
        $fieldName = $property->fieldName;
        $propertyName = $property->propertyName;
        $class = $property->reflection->getDeclaringClass()->getName();
        $globalIndex = $normalizerMap[$class][$fieldName] ?? null;
        $before = '';

        if ($property->normalizer !== null) {
            if ($property->normalizer instanceof ObjectNormalizer) {
                $valueCode = "\$this->doExtract(\$object->$propertyName, \$context, \$stack)";
            } elseif ($property->normalizer instanceof ArrayNormalizer) {
                $reflection = new ReflectionProperty($property->normalizer, 'normalizer');
                $inner = $reflection->getValue($property->normalizer);
                if ($inner instanceof ObjectNormalizer) {
                    $before = <<<PHP
\$$propertyName = \$object->$propertyName;
foreach (\$$propertyName as &\${$propertyName}Item) {
    \${$propertyName}Item = \$this->doExtract(\${$propertyName}Item, \$context, \$stack);
}
PHP;

                    $valueCode = "\$$propertyName";
                } else {
                    $valueCode = "\$this->n{$globalIndex}->normalize(\$object->$propertyName, \$context)";
                }
            } elseif ($globalIndex !== null) {
                $valueCode = "\$this->n{$globalIndex}->normalize(\$object->$propertyName, \$context)";
            } else {
                $valueCode = "\$object->$propertyName";
            }
        } else {
            $valueCode = "\$object->$propertyName";
        }

        return ["'$fieldName' => $valueCode,", $before];
    }

    private function padLeft(string $multilineString, int $n): string
    {
        $result = [];

        foreach (explode("\n", $multilineString) as $line) {
            $result[] = str_repeat(' ', $n * 4).$line;
        }

        return implode("\n", $result);
    }
}
