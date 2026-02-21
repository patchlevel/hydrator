<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Cryptography;

use function array_merge;

final class SubjectIds
{
    /** @param array<string, string> $subjectIds */
    public function __construct(
        public readonly array $subjectIds = [],
    ) {
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->subjectIds, $other->subjectIds));
    }

    public function get(string $name): string
    {
        return $this->subjectIds[$name] ?? throw new MissingSubjectId($name);
    }
}
