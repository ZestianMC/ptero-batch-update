<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Server;

final class SmokeTest extends TestCase
{
    public function testStubsAutoload(): void
    {
        self::assertSame('stub', (new Server())->name);
    }
}
