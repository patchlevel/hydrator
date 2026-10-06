<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class FinalClassesTest
{
    public function testFinalClasses(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('Patchlevel\Hydrator'),
                    Selector::NOT(Selector::isAbstract()),
                    Selector::NOT(Selector::isInterface()),
                ),
            )
            ->shouldBeFinal();
    }
}
