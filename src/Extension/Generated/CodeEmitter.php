<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use function array_filter;
use function array_unshift;
use function assert;
use function implode;
use function is_int;
use function sprintf;
use function var_export;

/**
 * Turns the plans of a transformer into php code.
 *
 * The generated transformer behaves like the {@see \Patchlevel\Hydrator\Transformer\ReflectionTransformer}:
 * objects are instantiated without their constructor and the properties are assigned directly. Assigning
 * properties happens inside closures bound to the scope of the class, so private and readonly properties
 * work the same way as with reflection.
 *
 * The emitter accumulates the members of a single generated class, create a new instance for every transformer.
 *
 * @internal
 */
final class CodeEmitter
{
    /** @var list<string> property declarations of the generated class */
    private array $properties = [];

    /** @var list<string> init methods, one per class */
    private array $inits = [];

    /** @var list<string> hydrate/extract methods which do not need the scope of a class */
    private array $methods = [];

    private int $helperSlots = 0;

    /** @param list<ClassPlan> $classes indexed by {@see ClassPlan::$index} */
    public function __construct(
        private readonly array $classes,
    ) {
    }

    /** The first plan is the class of the transformer, the others are nested classes which can be inlined. */
    public function emit(string $namespace, string $className): string
    {
        $constructor = [];

        foreach ($this->classes as $plan) {
            $index = $plan->index;

            // until the class is initialized, the closures point to a stub which initializes the class first
            $constructor[] = sprintf(
                '$this->h%1$d = function (array $data, array $context, object|null $object = null): object { $this->init%1$d(); return ($this->h%1$d)($data, $context, $object); };',
                $index,
            );
            $constructor[] = sprintf(
                '$this->e%1$d = function (object $object, array $context): array { $this->init%1$d(); return ($this->e%1$d)($object, $context); };',
                $index,
            );

            $this->emitClass($plan);
        }

        $root = $this->classes[0];

        return Templates::render(Templates::TRANSFORMER, [
            'namespace' => $namespace,
            'className' => $className,
            'class' => $root->className(),
            'version' => (string)TransformerFiles::VERSION,
            'properties' => implode("\n", $this->properties),
            'constructor' => implode("\n", $constructor),
            'hydrateCall' => $this->nestedHydrateCall($root),
            'extractCall' => $this->nestedExtractCall($root),
            'inits' => implode("\n\n", $this->inits),
            'methods' => implode("\n\n", $this->methods),
        ]);
    }

    /** Emits the properties, the init method and the hydrate/extract code of one class. */
    private function emitClass(ClassPlan $plan): void
    {
        $class = $plan->className();
        $index = $plan->index;

        $this->declareProperties($plan);

        $init = [];

        // the fingerprint of the class is part of the file name and nested classes are checked before they are inlined
        $init[] = sprintf('$metadata = $this->hydrator->metadata(\\%s::class);', $class);
        $init[] = sprintf('$this->r%d = $metadata->reflection;', $index);
        $nestedInits = [];

        foreach ($plan->properties as $property) {
            if ($property->defaultSlot !== null) {
                $init[] = sprintf('$this->d%d = $this->promotedDefault($metadata, %s);', $property->defaultSlot, var_export($property->name, true));
            }

            if ($property->slot === null) {
                continue;
            }

            $init[] = sprintf('$this->n%d = $this->normalizer($metadata, %s);', $property->slot, var_export($property->name, true));

            if ($property->nested === null) {
                continue;
            }

            $nested = $this->nested($property);
            $fingerprint = var_export(ClassFingerprint::of($nested->metadata), true);

            $check = $property->kind === ValueKind::NestedObject ? 'inlineObject' : 'inlineArray';
            $init[] = sprintf('$this->ih%d = $this->%s($this->n%d, \\%s::class, %s, Skip::Hydrate);', $property->flag, $check, $property->slot, $nested->className(), $fingerprint);
            $init[] = sprintf('$this->ie%d = $this->%s($this->n%d, \\%s::class, %s, Skip::Extract);', $property->flag, $check, $property->slot, $nested->className(), $fingerprint);

            if ($property->kind === ValueKind::NestedArray) {
                $init[] = sprintf('if ($this->ih%1$d || $this->ie%1$d) { $this->n%2$d = $this->n%3$d->innerNormalizer(); }', $property->flag, $property->inner, $property->slot);
            }

            // the nested code is only initialized if it is inlined, outdated code is never touched
            $nestedInits[] = sprintf('if ($this->ih%1$d || $this->ie%1$d) { $this->init%2$d(); }', $property->flag, $nested->index);
        }

        [$hydrate, $hydrateHelpers] = $this->hydrateBody($plan);
        [$extract, $extractHelpers] = $this->extractBody($plan);

        foreach ($hydrateHelpers as $helper) {
            $init[] = $helper;
        }

        $init[] = $this->wrap($plan, 'h', 'hydrate', 'array $data, array $context, object|null $object = null', 'object', $hydrate, $plan->scopedHydrate);

        foreach ($extractHelpers as $helper) {
            $init[] = $helper;
        }

        $init[] = $this->wrap($plan, 'e', 'extract', 'object $object, array $context', 'array', $extract, $plan->scopedExtract);

        foreach ($nestedInits as $nestedInit) {
            $init[] = $nestedInit;
        }

        if (!$plan->leaf()) {
            $init[] = sprintf('$this->tracked%d = $this->recursive%d();', $index, $index);
            $this->properties[] = sprintf('public bool $tracked%d = true;', $index);
            $this->methods[] = $this->recursiveMethod($plan);
        }

        $this->inits[] = Templates::render(Templates::INIT, [
            'index' => (string)$index,
            'body' => implode("\n", $init),
        ]);
    }

    private function declareProperties(ClassPlan $plan): void
    {
        foreach ($plan->properties as $property) {
            if ($property->slot !== null) {
                $this->properties[] = sprintf('public Normalizer $n%d;', $property->slot);
            }

            if ($property->inner !== null) {
                $this->properties[] = sprintf('public ObjectNormalizer $n%d;', $property->inner);
            }

            if ($property->flag !== null) {
                $this->properties[] = sprintf('public bool $ih%d = false;', $property->flag);
                $this->properties[] = sprintf('public bool $ie%d = false;', $property->flag);
            }

            if ($property->defaultSlot === null) {
                continue;
            }

            $this->properties[] = sprintf('public ReflectionParameter $d%d;', $property->defaultSlot);
        }

        $index = $plan->index;

        $this->properties[] = sprintf('public bool $ready%d = false;', $index);
        $this->properties[] = sprintf('public bool $initializing%d = false;', $index);
        $this->properties[] = sprintf('public bool|null $recursive%d = null;', $index);
        $this->properties[] = sprintf('public Closure $h%d;', $index);
        $this->properties[] = sprintf('public Closure $e%d;', $index);
        $this->properties[] = sprintf('public ReflectionClass $r%d;', $index);
    }

    /**
     * Circular references are only possible through normalizers which call the hydrator again. A class whose
     * normalizers can not reach the class itself again does not need the (expensive) call stack tracking.
     * Unknown paths and cycles are conservatively treated as recursive.
     */
    private function recursiveMethod(ClassPlan $plan): string
    {
        $checks = [];

        foreach ($plan->properties as $property) {
            if ($property->kind === ValueKind::Raw) {
                continue;
            }

            if ($property->nested === null) {
                $checks[] = sprintf('$this->mayRecurse($this->n%d)', $property->slot);

                continue;
            }

            $nested = $this->nested($property);
            $recursive = $nested->leaf() ? 'false' : sprintf('$this->recursive%d()', $nested->index);
            $checks[] = sprintf('($this->ie%d ? %s : $this->mayRecurse($this->n%d))', $property->flag, $recursive, $property->slot);
        }

        return Templates::render(Templates::RECURSIVE, [
            'index' => (string)$plan->index,
            'checks' => implode("\n    || ", $checks),
        ]);
    }

    /**
     * Emits the body either as closure bound to the class scope (assigned in init) or as a plain method.
     *
     * @return string init code which assigns the closure
     */
    private function wrap(ClassPlan $plan, string $slot, string $name, string $signature, string $return, string $body, bool $scoped): string
    {
        $index = $plan->index;

        if ($scoped) {
            return Templates::render(Templates::CLOSURE, [
                'slot' => $slot . $index,
                'signature' => $signature,
                'return' => $return,
                'body' => $body,
                'class' => $plan->className(),
            ]);
        }

        $this->methods[] = Templates::render(Templates::METHOD, [
            'name' => $name . $index,
            'signature' => $signature,
            'return' => $return,
            'body' => $body,
        ]);

        return sprintf('$this->%s%d = $this->%s%d(...);', $slot, $index, $name, $index);
    }

    /**
     * Generates the hydrate code, properties which need another scope are assigned in a helper closure.
     *
     * @return array{string, list<string>} body and init code of the helper closures
     */
    private function hydrateBody(ClassPlan $plan): array
    {
        $main = [];
        $scoped = [];

        foreach ($plan->properties as $property) {
            if ($property->hydrateScope !== null) {
                $scoped[$property->hydrateScope][] = $this->assignment($plan, $property);

                continue;
            }

            $main[] = $this->assignment($plan, $property);
        }

        $helpers = [];

        foreach ($scoped as $scope => $code) {
            $slot = $this->helperSlots++;
            $this->properties[] = sprintf('public Closure $hp%d;', $slot);
            $helpers[] = Templates::render(Templates::HYDRATE_HELPER, [
                'slot' => (string)$slot,
                'scope' => $scope,
                'body' => implode("\n", $code),
            ]);
            $main[] = sprintf('($this->hp%d)($object, $data, $context);', $slot);
        }

        $body = [sprintf('$object ??= $this->r%d->newInstanceWithoutConstructor();', $plan->index)];
        $body[] = implode("\n\n", $main);
        $body[] = 'return $object;';

        return [
            implode("\n\n", array_filter($body, static fn (string $code): bool => $code !== '')),
            $helpers,
        ];
    }

    private function assignment(ClassPlan $plan, PropertyPlan $property): string
    {
        $template = $property->hasDefault() ? Templates::ASSIGN_DEFAULT : Templates::ASSIGN;

        return Templates::render($template, [
            'field' => var_export($property->field, true),
            'name' => $property->name,
            'property' => var_export($property->name, true),
            'class' => $plan->className(),
            'value' => $this->denormalize($plan, $property, '$value'),
            'default' => $property->default ?? sprintf('$this->d%d->getDefaultValue()', $property->defaultSlot),
        ]);
    }

    /** Code which denormalizes the field into the given variable. */
    private function denormalize(ClassPlan $plan, PropertyPlan $property, string $variable): string
    {
        $field = var_export($property->field, true);

        if ($property->kind === ValueKind::Raw) {
            return sprintf('%s = $data[%s];', $variable, $field);
        }

        $inner = match ($property->kind) {
            ValueKind::Normalizer => sprintf('%s = $this->n%d->denormalize($data[%s], $context);', $variable, $property->slot, $field),
            ValueKind::NestedObject => Templates::render(Templates::DENORMALIZE_OBJECT, [
                'variable' => $variable,
                'field' => $field,
                'flag' => (string)$property->flag,
                'slot' => (string)$property->slot,
                'nested' => $this->nestedHydrateCall($this->nested($property)),
            ]),
            ValueKind::NestedArray => Templates::render(Templates::DENORMALIZE_ARRAY, [
                'variable' => $variable,
                'field' => $field,
                'flag' => (string)$property->flag,
                'slot' => (string)$property->slot,
                'inner' => (string)$property->inner,
                'nested' => $this->nestedHydrateCall($this->nested($property)),
            ]),
        };

        return Templates::render(Templates::DENORMALIZE, [
            'inner' => $inner,
            'class' => $plan->className(),
            'property' => var_export($property->name, true),
            'slot' => (string)$property->slot,
        ]);
    }

    /**
     * Generates the extract code, properties which need another scope are read in a helper closure.
     *
     * @return array{string, list<string>} body and init code of the helper closures
     */
    private function extractBody(ClassPlan $plan): array
    {
        $pre = [];
        $scoped = [];
        $fields = [];

        foreach ($plan->properties as $property) {
            $field = var_export($property->field, true);
            [$code, $expression] = $this->normalize($plan, $property);

            if ($property->extractScope !== null) {
                $scoped[$property->extractScope][] = [$property->name, $code, $expression];
                $fields[] = sprintf('%s => $v_%s,', $field, $property->name);

                continue;
            }

            if ($code !== '') {
                $pre[] = $code;
            }

            $fields[] = sprintf('%s => %s,', $field, $expression);
        }

        $helpers = [];

        foreach ($scoped as $scope => $items) {
            $slot = $this->helperSlots++;
            $this->properties[] = sprintf('public Closure $ep%d;', $slot);

            $body = [];
            $values = [];
            $variables = [];

            foreach ($items as [$name, $code, $expression]) {
                if ($code !== '') {
                    $body[] = $code;
                }

                $values[] = $expression;
                $variables[] = sprintf('$v_%s', $name);
            }

            $body[] = sprintf('return [%s];', implode(', ', $values));

            $helpers[] = Templates::render(Templates::EXTRACT_HELPER, [
                'slot' => (string)$slot,
                'scope' => $scope,
                'body' => implode("\n", $body),
            ]);

            array_unshift($pre, sprintf('[%s] = ($this->ep%d)($object, $context);', implode(', ', $variables), $slot));
        }

        $variables = [
            'index' => (string)$plan->index,
            'class' => $plan->className(),
            'pre' => implode("\n\n", $pre),
            'fields' => implode("\n", $fields),
        ];

        if ($plan->leaf()) {
            return [Templates::render(Templates::EXTRACT_PLAIN, $variables), $helpers];
        }

        return [
            Templates::render(Templates::EXTRACT_TRACKED, $variables) . "\n\n" . Templates::render(Templates::EXTRACT_PLAIN, $variables),
            $helpers,
        ];
    }

    /** @return array{string, string} preparing code and the expression for the normalized value */
    private function normalize(ClassPlan $plan, PropertyPlan $property): array
    {
        $name = $property->name;

        if ($property->kind === ValueKind::Raw) {
            return ['', sprintf('$object->%s', $name)];
        }

        $variable = sprintf('$v_%s', $name);

        $inner = match ($property->kind) {
            ValueKind::Normalizer => sprintf('%s = $this->n%d->normalize($object->%s, $context);', $variable, $property->slot, $name),
            ValueKind::NestedObject => Templates::render(Templates::NORMALIZE_OBJECT, [
                'variable' => $variable,
                'name' => $name,
                'flag' => (string)$property->flag,
                'slot' => (string)$property->slot,
                'nested' => $this->nestedExtractCall($this->nested($property)),
                'check' => $this->instanceCheck('$value', $property),
            ]),
            ValueKind::NestedArray => Templates::render(Templates::NORMALIZE_ARRAY, [
                'variable' => $variable,
                'name' => $name,
                'flag' => (string)$property->flag,
                'slot' => (string)$property->slot,
                'inner' => (string)$property->inner,
                'nested' => $this->nestedExtractCall($this->nested($property)),
                'check' => $this->instanceCheck('$item', $property),
            ]),
        };

        $code = Templates::render(Templates::NORMALIZE, [
            'inner' => $inner,
            'class' => $plan->className(),
            'property' => var_export($name, true),
            'slot' => (string)$property->slot,
        ]);

        return [$code, $variable];
    }

    /** Only exact instances are inlined, subclasses take the generic path through the hydrator. */
    private function instanceCheck(string $variable, PropertyPlan $property): string
    {
        $nested = $this->nested($property);
        $class = $nested->className();

        if ($nested->metadata->reflection->isFinal()) {
            return sprintf('%s instanceof \\%s', $variable, $class);
        }

        return sprintf('%1$s instanceof \\%2$s && %1$s::class === \\%2$s::class', $variable, $class);
    }

    private function nestedHydrateCall(ClassPlan $nested): string
    {
        return $nested->scopedHydrate ? sprintf('($this->h%d)', $nested->index) : sprintf('$this->hydrate%d', $nested->index);
    }

    private function nestedExtractCall(ClassPlan $nested): string
    {
        return $nested->scopedExtract ? sprintf('($this->e%d)', $nested->index) : sprintf('$this->extract%d', $nested->index);
    }

    private function nested(PropertyPlan $property): ClassPlan
    {
        assert(is_int($property->nested));

        return $this->classes[$property->nested];
    }
}
