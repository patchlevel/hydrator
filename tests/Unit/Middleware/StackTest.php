<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Middleware;

use Patchlevel\Hydrator\Middleware\Middleware;
use Patchlevel\Hydrator\Middleware\NoMoreMiddleware;
use Patchlevel\Hydrator\Middleware\Stack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stack::class)]
final class StackTest extends TestCase
{
    public function testEmptyStack(): void
    {
        $this->expectException(NoMoreMiddleware::class);

        $stack = new Stack([]);
        $stack->next();
    }

    public function testStack(): void
    {
        $middleware1 = $this->createStub(Middleware::class);
        $middleware2 = $this->createStub(Middleware::class);

        $stack = new Stack([$middleware1, $middleware2]);

        self::assertSame($middleware1, $stack->next());
        self::assertSame($middleware2, $stack->next());
    }
}
