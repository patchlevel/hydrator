<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use UnitEnum;

use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function ord;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function var_export;

/**
 * Writes a value as php code. Only scalars, enums and arrays of them can be written, everything else
 * (objects for example) has to be resolved at runtime.
 *
 * @internal
 */
final class Literal
{
    /** @return string|null php expression for the value, null if it can not be expressed as a literal */
    public static function export(mixed $value): string|null
    {
        if (is_string($value)) {
            return self::string($value);
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return var_export($value, true);
        }

        if ($value instanceof UnitEnum) {
            return sprintf('\\%s::%s', $value::class, $value->name);
        }

        if (is_array($value)) {
            $items = [];

            foreach ($value as $key => $item) {
                $literal = self::export($item);

                if ($literal === null) {
                    return null;
                }

                $items[] = sprintf('%s => %s', is_string($key) ? self::string($key) : var_export($key, true), $literal);
            }

            return '[' . implode(', ', $items) . ']';
        }

        return null;
    }

    /**
     * var_export() writes control characters like newlines verbatim, which breaks the indentation of the
     * generated code. Such strings are written as double quoted literals with escape sequences instead.
     */
    private static function string(string $value): string
    {
        if (preg_match('/[\x00-\x1f\x7f]/', $value) !== 1) {
            return var_export($value, true);
        }

        $escaped = preg_replace_callback(
            '/[\x00-\x1f\x7f"\\\\$]/',
            static fn (array $matches): string => match ($matches[0]) {
                '"' => '\\"',
                '\\' => '\\\\',
                '$' => '\\$',
                default => sprintf('\\x%02X', ord($matches[0])),
            },
            $value,
        );

        return '"' . $escaped . '"';
    }
}
