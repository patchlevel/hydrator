<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Architecture;

use Patchlevel\Hydrator\HydratorException;
use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ExceptionImplementsHydratorExceptionTest
{
    public function testExceptions(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('Patchlevel\Hydrator'),
                    Selector::isException(),
                ),
            )
            ->shouldImplement()->classes(Selector::classname(HydratorException::class));
    }
}
