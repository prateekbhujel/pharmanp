<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelloTest extends TestCase
{
    public function test_returns_hello_world(): void
    {
        $this->assertSame('Hello, World!', 'Hello, World!');
    }
}
