<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class MultilineDefaultDto
{
    /** @param array<string, string> $lines */
    public function __construct(
        // phpcs:disable Squiz.Functions.MultiLineFunctionDeclaration.Indent -- the sniff does not understand the nowdoc body
        public string $name = <<<'TEXT'
            foo bar
            baz
            TEXT,
        // phpcs:enable
        public string $text = "first\nsecond",
        public array $lines = ["a\tb" => "c\nd \$e \"f\""],
    ) {
    }
}
