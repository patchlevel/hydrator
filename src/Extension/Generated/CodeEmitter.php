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
 * Turns the plan of a class into the php code of its transformer.
 *
 * The generated transformer behaves like the {@see \Patchlevel\Hydrator\Transformer\ReflectionTransformer}:
 * objects are instantiated without their constructor and the properties are assigned directly. Assigning
 * properties happens inside closures bound to the scope of the class, so private and readonly properties
 * work the same way as with reflection.
 *
 * The emitter accumulates the members of a single transformer, create a new instance for every class.
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
        $nestedInits = [];

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

            $this->properties[] = sprintf('public ClassTransformer|HydrateHandler|null $hh%d = null;', $flag);
            $this->properties[] = sprintf('public ClassTransformer|ExtractHandler|null $he%d = null;', $flag);
            $this->properties[] = sprintf('public bool $ih%d = false;', $flag);
            $this->properties[] = sprintf('public bool $ie%d = false;', $flag);
            $initialize[] = sprintf('$this->hh%d = $this->handler($this->n%d, \\%s::class, Direction::Hydrate);', $flag, $property->slot, $nested->class);
            $initialize[] = sprintf('$this->he%d = $this->handler($this->n%d, \\%s::class, Direction::Extract);', $flag, $property->slot, $nested->class);
            // generated transformers of nested classes are mapped in place, all others are called through their handler
            $initialize[] = sprintf('$this->ih%1$d = $this->hh%1$d instanceof GeneratedTransformer;', $flag);
            $initialize[] = sprintf('$this->ie%1$d = $this->he%1$d instanceof GeneratedTransformer;', $flag);
            $this->properties[] = sprintf('public Closure|null $nh%d = null;', $flag);
            $this->properties[] = sprintf('public Closure|null $ne%d = null;', $flag);
            $nestedInits[] = sprintf('if ($this->ih%1$d) { $this->hh%1$d->init(); $this->nh%1$d = $this->hh%1$d->nestedHydrator; }', $flag);
            $nestedInits[] = sprintf('if ($this->ie%1$d) { $this->he%1$d->init(); $this->ne%1$d = $this->he%1$d->nestedExtractor; }', $flag);

            if ($property->inner === null) {
                continue;
            }

            $this->properties[] = sprintf('public ObjectNormalizer $n%d;', $property->inner);
            $initialize[] = sprintf('if ($this->hh%1$d !== null || $this->he%1$d !== null) { $this->n%2$d = $this->n%3$d->innerNormalizer(); }', $flag, $property->inner, $property->slot);
        }

        [$hydrate, $hydrateHelpers] = $this->hydrateBody();
        [$extract, $extractHelpers] = $this->extractBody();

        foreach ([...$hydrateHelpers, ...$extractHelpers] as $helper) {
            $initialize[] = $helper;
        }

        // the nested entry points: called directly by the transformers of other classes, without the entry checks
        $nestedHydrate = "\$object = \$this->reflection->newInstanceWithoutConstructor();\n\n" . self::withInline($hydrate, 'true');
        $nestedExtract = self::withInline($extract, 'true');
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
        }

        if ($plan->scopedHydrate) {
            $initialize[] = Templates::render(Templates::CLOSURE, [
                'slot' => 'nestedHydrator',
                'signature' => 'array $data, array $context',
                'return' => 'object',
                'body' => $nestedHydrate,
                'class' => $plan->className(),
            ]);
            $nestedHydrate = 'return ($this->nestedHydrator)($data, $context);';
        }

        if ($plan->scopedExtract) {
            $initialize[] = Templates::render(Templates::CLOSURE, [
                'slot' => 'nestedExtractor',
                'signature' => 'object $object, array $context',
                'return' => 'array',
                'body' => $nestedExtract,
                'class' => $plan->className(),
            ]);
            $nestedExtract = 'return ($this->nestedExtractor)($object, $context);';
        }

        $nestedMethods = [
            Templates::render(Templates::NESTED_HYDRATE, ['body' => $nestedHydrate]),
            Templates::render(Templates::NESTED_EXTRACT, ['body' => $nestedExtract]),
        ];

        // the nested transformers are initialized last, they may lead back to this one
        foreach ($nestedInits as $init) {
            $initialize[] = $init;
        }

        $code = Templates::render(Templates::TRANSFORMER, [
            'namespace' => $namespace,
            'className' => $className,
            'class' => $plan->className(),
            'version' => (string)TransformerFiles::VERSION,
            'properties' => implode("\n", $this->properties),
            // the entry points of the hydrator check whether nested objects can be mapped in place, the nested entry
            // points are only called if they can
            'hydrate' => self::withInline($hydrate, $plan->nested() === [] ? 'false' : Templates::OWNER),
            'extract' => self::withInline($extract, $plan->nested() === [] ? 'false' : Templates::OWNER),
            'nestedMethods' => implode("\n\n", $nestedMethods),
            'initialize' => $initialize === [] ? '// nothing to resolve' : implode("\n", $initialize),
            'recursive' => $this->recursive(),
        ]);

        // empty placeholders leave blank lines behind
        $code = preg_replace(["/\n{3,}/", "/{\n\n/"], ["\n\n", "{\n"], $code);
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
                '($this->ie%1$d ? $this->he%1$d->recursive() : self::mayRecurse($this->n%2$d))',
                $property->flag,
                $property->slot,
            );
        }

        return $checks === [] ? 'false' : implode("\n            || ", $checks);
    }

    /**
     * Generates the hydrate code, properties which need another scope are assigned in a helper closure.
     *
     * @return array{string, list<string>} body and init code of the helper closures
     */
    private function hydrateBody(): array
    {
        $main = [];
        $scoped = [];

        foreach ($this->plan->properties as $property) {
            if ($property->hydrateScope !== null) {
                $scoped[$property->hydrateScope][] = $this->assignment($property);

                continue;
            }

            $main[] = $this->assignment($property);
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

    private function assignment(PropertyPlan $property): string
    {
        $template = $property->hasDefault() ? Templates::ASSIGN_DEFAULT : Templates::ASSIGN;

        return Templates::render($template, [
            'field' => var_export($property->field, true),
            'name' => $property->name,
            'property' => var_export($property->name, true),
            'class' => $this->plan->className(),
            'value' => $this->denormalize($property, '$value'),
            'default' => $property->default ?? sprintf('$this->d%d->getDefaultValue()', $property->defaultSlot),
        ]);
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
            ]),
            ValueKind::NestedArray => Templates::render(Templates::DENORMALIZE_ARRAY, [
                'variable' => $variable,
                'field' => $field,
                'flag' => (string)$property->flag,
                'slot' => (string)$property->slot,
                'inner' => (string)$property->inner,
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

        $variables = [
            'class' => $this->plan->className(),
            'pre' => implode("\n\n", $pre),
            'fields' => implode("\n", $fields),
        ];

        if ($this->plan->leaf()) {
            return [Templates::render(Templates::EXTRACT_PLAIN, $variables), $helpers];
        }

        return [
            Templates::render(Templates::EXTRACT_TRACKED, $variables) . "\n\n"
                . Templates::render(Templates::EXTRACT_PLAIN, $variables),
            $helpers,
        ];
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
                'check' => self::instanceCheck('$value', $property),
            ]),
            ValueKind::NestedArray => Templates::render(Templates::NORMALIZE_ARRAY, [
                'variable' => $variable,
                'name' => $name,
                'flag' => (string)$property->flag,
                'slot' => (string)$property->slot,
                'inner' => (string)$property->inner,
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
}
