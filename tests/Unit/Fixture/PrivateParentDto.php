<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

abstract class PrivateParentDto
{
    public function __construct(
        #[EmailNormalizer]
        private readonly Email $email,
        private string $note,
    ) {
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function note(): string
    {
        return $this->note;
    }
}
