<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class PrivateChildDto extends PrivateParentDto
{
    public function __construct(
        #[IdNormalizer]
        public readonly ProfileId $profileId,
        Email $email,
        string $note,
        private string $name,
    ) {
        parent::__construct($email, $note);
    }

    public function name(): string
    {
        return $this->name;
    }
}
