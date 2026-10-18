<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use function array_unshift;
use function assert;
use function implode;
use function preg_replace;
use function sprintf;
use function str_contains;
use function var_export;

/**
 * Turns the plan of a class into the php code of its mapper.
 *
 * The generated mapper behaves like the {@see \Patchlevel\Hydrator\Mapper\ReflectionMapper}:
 * objects are instantiated without their constructor and the properties are assigned directly. Assigning
 * properties happens inside closures bound to the scope of the class, so private and readonly properties
 * work the same way as with reflection.
 *
 * The emitter accumulates the members of a single mapper, create a new instance for every class.
 *
 * @internal
 */
final class CodeEmitter
{
    /** @var list<string> property declarations of the generated class */
    private array $properties = [];

    private int $helperSlots = 0;

    public function __construct(
        private readonly ClassPlan $plan,
    ) {
    }

    public function emit(string $namespace, string $className): string
    {
        $plan = $this->plan;
        $initialize = [];

        foreach ($plan->properties as $property) {
            if ($property->defaultSlot !== null) {
                $this->properties[] = sprintf('public ReflectionParameter $d%d;', $property->defaultSlot);
                $initialize[] = sprintf('$this->d%d = $this->promotedDefault(%s);', $property->defaultSlot, var_export($property->name, true));
            }

            if ($property->slot === null) {
                continue;
            }

            $this->properties[] = sprintf('public Normalizer $n%d;', $property->slot);
            $initialize[] = sprintf('$this->n%d = $this->normalizer(%s);', $property->slot, var_export($property->name, true));

            if ($property->nested === null || $property->flag === null) {
                continue;
            }

            $flag = $property->flag;
            $nested = $property->nested;

            $this->properties[] = sprintf('public bool $i%d = false;', $flag);
            $initialize[] = sprintf('$m%d = $this->nested($this->n%d, \\%s::class);', $flag, $property->slot, $nested->class);
            $initialize[] = sprintf('$this->i%1$d = $m%1$d !== null;', $flag);
            $initialize[] = sprintf('if ($m%1$d !== null) { $this->x%2$d = $m%1$d; }', $flag, $nested->slot);

            if ($property->inner === null) {
                continue;
            }

            $this->properties[] = sprintf('public ObjectNormalizer $n%d;', $property->inner);
            $initialize[] = sprintf('if ($this->i%1$d) { $this->n%2$d = $this->n%3$d->innerNormalizer(); }', $flag, $property->inner, $property->slot);
        }

        foreach ($plan->nested() as $slot => $nested) {
            $this->properties[] = sprintf('public NestedMapper $x%d;', $slot);
        }

        [$hydrate, $hydrateHelpers] = $this->hydrateBody(true);
        [$populate, $populateHelpers] = $plan->defaults() ? $this->hydrateBody(false) : ['', []];
        [$extract, $extractHelpers] = $this->extractBody();
        [$extractFiltered, $filteredHelpers] = $this->extractFilteredBody();

        foreach ([...$hydrateHelpers, ...$populateHelpers, ...$extractHelpers, ...$filteredHelpers] as $helper) {
            $initialize[] = $helper;
        }

        if ($plan->scopedHydrate) {
            $this->properties[] = 'public Closure $h;';
            $initialize[] = Templates::render(Templates::CLOSURE, [
                'slot' => 'h',
                'signature' => 'object $object, array $data, array $context, bool $inline',
                'return' => 'object',
                'body' => $hydrate,
                'class' => $plan->className(),
            ]);
            $hydrate = 'return ($this->h)($object, $data, $context, $inline);';

            if ($populate !== '') {
                $this->properties[] = 'public Closure $p;';
                $initialize[] = Templates::render(Templates::CLOSURE, [
                    'slot' => 'p',
                    'signature' => 'object $object, array $data, array $context, bool $inline',
                    'return' => 'object',
                    'body' => $populate,
                    'class' => $plan->className(),
                ]);
                $populate = 'return ($this->p)($object, $data, $context, $inline);';
            }
        }

        if ($plan->scopedExtract) {
            $this->properties[] = 'public Closure $e;';
            $initialize[] = Templates::render(Templates::CLOSURE, [
                'slot' => 'e',
                'signature' => 'object $object, array $context, bool $inline',
                'return' => 'array',
                'body' => $extract,
                'class' => $plan->className(),
            ]);
            $extract = 'return ($this->e)($object, $context, $inline);';

            $this->properties[] = 'public Closure $f;';
            $initialize[] = Templates::render(Templates::CLOSURE, [
                'slot' => 'f',
                'signature' => 'object $object, array $context, array $fields, bool $inline',
                'return' => 'array',
                'body' => $extractFiltered,
                'class' => $plan->className(),
            ]);
            $extractFiltered = 'return ($this->f)($object, $context, $fields, $inline);';
        }

        foreach ($plan->nested() as $slot => $nested) {
            // the nested mappers are initialized last, they may lead back to this one
            $initialize[] = sprintf('self::initNested($this->x%d ?? null);', $slot);
        }

        $code = Templates::render(Templates::MAPPER, [
            'namespace' => $namespace,
            'className' => $className,
            'class' => $plan->className(),
            'version' => (string)MapperFiles::VERSION,
            'properties' => implode("\n", $this->properties),
            // nested objects are only mapped right away for calls of the hydrator which created the mapper
            'hydrate' => self::withInline($hydrate, $plan->nested() === [] ? 'false' : Templates::OWNER),
            // existing objects keep the values of missing fields, only classes with promoted defaults need own code
            'populateCall' => $populate === '' ? '' : Templates::POPULATE_CALL,
            'populate' => $populate === '' ? '' : Templates::render(Templates::POPULATE, [
                'body' => self::withInline($populate, $plan->nested() === [] ? 'false' : Templates::OWNER),
            ]),
            'extract' => self::withInline($extract, $plan->nested() === [] ? 'false' : Templates::OWNER),
            // the nested entry points are only called by a parent mapper which already did these checks
            'hydrateNested' => self::withInline($hydrate, 'true'),
            'extractNested' => self::withInline($extract, 'true'),
            'extractFiltered' => $extractFiltered,
            'initialize' => $initialize === [] ? '// nothing to resolve' : implode("\n", $initialize),
            'recursive' => $this->recursive(),
        ]);

        // empty placeholders leave blank lines behind
        $code = preg_replace(["/\n{3,}/", "/{\n\n/", "/\n\n([ \t]*})/"], ["\n\n", "{\n", "\n$1"], $code);
        assert($code !== null);

        return $code;
    }

    /** Code without nested objects does not use $inline, the code of scoped classes gets it as argument. */
    private static function withInline(string $code, string $inline): string
    {
        if (!str_contains($code, '$inline')) {
            return $code;
        }

        return sprintf("\$inline = %s;\n\n%s", $inline, $code);
    }

    /**
     * Circular references are only possible through normalizers which extract objects with the hydrator. A class
     * whose normalizers can not reach the class itself again does not need the (expensive) call stack tracking.
     */
    private function recursive(): string
    {
        $checks = [];

        foreach ($this->plan->properties as $property) {
            if ($property->kind === ValueKind::Raw) {
                continue;
            }

            if ($property->nested === null) {
                $checks[] = sprintf('self::mayRecurse($this->n%d)', $property->slot);

                continue;
            }

            $checks[] = sprintf(
                '($this->i%d ? self::reaches($this->x%d) : self::mayRecurse($this->n%d))',
                $property->flag,
                $property->nested->slot,
                $property->slot,
            );
        }

        return $checks === [] ? 'false' : implode("\n            || ", $checks);
    }

    /**
     * Generates the hydrate code, properties which need another scope are assigned in a helper closure.
     *
     * @param bool $defaults whether missing fields fall back to the default of a promoted property, only for new objects
     *
     * @return array{string, list<string>} body and init code of the helper closures
     */
    private function hydrateBody(bool $defaults): array
    {
        $main = [];
        $scoped = [];

        foreach ($this->plan->properties as $property) {
            if ($property->hydrateScope !== null) {
                $scoped[$property->hydrateScope][] = $this->assignment($property, $defaults);

                continue;
            }

            $main[] = $this->assignment($property, $defaults);
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
            $main[] = sprintf('($this->hp%d)($object, $data, $context, $inline);', $slot);
        }

        $main[] = 'return $object;';

        return [implode("\n\n", $main), $helpers];
    }

    private function assignment(PropertyPlan $property, bool $defaults): string
    {
        $template = $defaults && $property->hasDefault() ? Templates::ASSIGN_DEFAULT : Templates::ASSIGN;
        $code = [];

        // the first alias in the data takes the place of the missing field
        foreach ($property->aliases as $alias) {
            $code[] = Templates::render(Templates::ALIAS, [
                'field' => var_export($property->field, true),
                'alias' => var_export($alias, true),
            ]);
        }

        $code[] = Templates::render($template, [
            'field' => var_export($property->field, true),
            'name' => $property->name,
            'property' => var_export($property->name, true),
            'class' => $this->plan->className(),
            'value' => $this->denormalize($property, '$value'),
            'default' => $property->default ?? sprintf('$this->d%d->getDefaultValue()', $property->defaultSlot),
        ]);

        return implode("\n\n", $code);
    }

    /** Code which denormalizes the field into the given variable. */
    private function denormalize(PropertyPlan $property, string $variable): string
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
                'nested' => $this->nestedCall($property, 'hydrate', $variable),
            ]),
            ValueKind::NestedArray => Templates::render(Templates::DENORMALIZE_ARRAY, [
                'variable' => $variable,
                'field' => $field,
                'flag' => (string)$property->flag,
                'slot' => (string)$property->slot,
                'inner' => (string)$property->inner,
                'nested' => $this->nestedCall($property, 'hydrate', '$item'),
            ]),
        };

        return Templates::render(Templates::DENORMALIZE, [
            'inner' => $inner,
            'class' => $this->plan->className(),
            'property' => var_export($property->name, true),
            'slot' => (string)$property->slot,
        ]);
    }

    /**
     * Generates the extract code, properties which need another scope are read in a helper closure.
     *
     * @return array{string, list<string>} body and init code of the helper closures
     */
    private function extractBody(): array
    {
        $pre = [];
        $scoped = [];
        $fields = [];

        foreach ($this->plan->properties as $property) {
            $field = var_export($property->field, true);
            [$code, $expression] = $this->normalize($property);

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

            array_unshift($pre, sprintf('[%s] = ($this->ep%d)($object, $context, $inline);', implode(', ', $variables), $slot));
        }

        $body = Templates::render(Templates::EXTRACT_PLAIN, [
            'pre' => implode("\n\n", $pre),
            'fields' => implode("\n", $fields),
        ]);

        return [$this->tracked($body), $helpers];
    }

    /**
     * Generates the extract code for calls with a property filter, it only reads the properties in $fields. Properties
     * which need another scope are read in a helper closure, which only returns the values of the kept properties.
     *
     * @return array{string, list<string>} body and init code of the helper closures
     */
    private function extractFilteredBody(): array
    {
        $fields = [];
        $scoped = [];

        /** @var array<class-string, int> $slots */
        $slots = [];

        foreach ($this->plan->properties as $property) {
            $field = var_export($property->field, true);
            [$code, $expression] = $this->normalize($property);
            $block = Templates::render(Templates::EXTRACT_FILTERED_FIELD, [
                'field' => $field,
                'code' => $code,
                'value' => $expression,
            ]);

            if ($property->extractScope === null) {
                $fields[] = $block;

                continue;
            }

            $scope = $property->extractScope;
            $slot = $slots[$scope] ??= $this->helperSlots++;
            $scoped[$scope][] = $block;
            $fields[] = Templates::render(Templates::EXTRACT_FILTERED_FIELD, [
                'field' => $field,
                'code' => '',
                'value' => sprintf('$s%d[%s]', $slot, $field),
            ]);
        }

        $pre = [];
        $helpers = [];

        foreach ($scoped as $scope => $blocks) {
            $slot = $slots[$scope];
            $this->properties[] = sprintf('public Closure $fp%d;', $slot);

            $helpers[] = Templates::render(Templates::EXTRACT_FILTERED_HELPER, [
                'slot' => (string)$slot,
                'scope' => $scope,
                'body' => implode("\n\n", $blocks),
            ]);

            $pre[] = sprintf('$s%1$d = ($this->fp%1$d)($object, $context, $fields, $inline);', $slot);
        }

        $body = Templates::render(Templates::EXTRACT_FILTERED, [
            'pre' => implode("\n", $pre),
            'fields' => implode("\n\n", $fields),
        ]);

        return [$this->tracked($body), $helpers];
    }

    /** Classes which may lead back to themselves track the extracted objects to detect circular references. */
    private function tracked(string $body): string
    {
        if ($this->plan->leaf()) {
            return $body;
        }

        return Templates::render(Templates::EXTRACT_TRACKED, [
            'class' => $this->plan->className(),
            'body' => $body,
        ]) . "\n\n" . $body;
    }

    /** @return array{string, string} preparing code and the expression for the normalized value */
    private function normalize(PropertyPlan $property): array
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
                'nested' => $this->nestedCall($property, 'extract', '$value'),
                'check' => self::instanceCheck('$value', $property),
            ]),
            ValueKind::NestedArray => Templates::render(Templates::NORMALIZE_ARRAY, [
                'variable' => $variable,
                'name' => $name,
                'flag' => (string)$property->flag,
                'slot' => (string)$property->slot,
                'inner' => (string)$property->inner,
                'nested' => $this->nestedCall($property, 'extract', '$item'),
                'check' => self::instanceCheck('$item', $property),
            ]),
        };

        $code = Templates::render(Templates::NORMALIZE, [
            'inner' => $inner,
            'class' => $this->plan->className(),
            'property' => var_export($name, true),
            'slot' => (string)$property->slot,
        ]);

        return [$code, $variable];
    }

    /** Only exact instances are mapped in place, subclasses take the generic path through the hydrator. */
    private static function instanceCheck(string $variable, PropertyPlan $property): string
    {
        $nested = $property->nested;
        assert($nested !== null);

        if ($nested->final) {
            return sprintf('%s instanceof \\%s', $variable, $nested->class);
        }

        return sprintf('%1$s instanceof \\%2$s && %1$s::class === \\%2$s::class', $variable, $nested->class);
    }

    /** The call of the nested entry point, it skips the checks this mapper already did. */
    private function nestedCall(PropertyPlan $property, string $direction, string $value): string
    {
        assert($property->nested !== null);

        return sprintf('$this->x%d->%sNested(%s, $context)', $property->nested->slot, $direction, $value);
    }
}
