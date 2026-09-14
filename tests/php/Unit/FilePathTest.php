<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\FilePath;

final class FilePathTest extends TestCase
{
    public function testAcceptsNormalAbsolutePath(): void
    {
        self::assertNull(FilePath::validate('/plugins/zCosmetics/cosmetics/balloons.yml'));
    }

    public function testAcceptsRootLevelFile(): void
    {
        self::assertNull(FilePath::validate('/server.properties'));
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidPaths(): iterable
    {
        yield 'empty' => ['', 'The file path is required.'];
        yield 'not a string' => [['a'], 'The file path is required.'];
        yield 'relative' => ['plugins/x.yml', 'The file path must start with /.'];
        yield 'trailing slash' => ['/plugins/', 'The file path must not end with /.'];
        yield 'root only' => ['/', 'The file path must not end with /.'];
        yield 'dotdot segment' => ['/plugins/../x.yml', 'The file path must not contain .. segments.'];
        yield 'dotdot at end' => ['/plugins/..', 'The file path must not contain .. segments.'];
        yield 'nul byte' => ["/plugins/x\0.yml", 'The file path contains invalid characters.'];
        yield 'too long' => ['/' . str_repeat('a', 4096), 'The file path is too long.'];
    }

    /** @dataProvider invalidPaths */
    public function testRejectsInvalidPaths(mixed $path, string $expected): void
    {
        self::assertSame($expected, FilePath::validate($path));
    }

    public function testDotDotInsideNameIsAllowed(): void
    {
        // "foo..bar" is a legal file name; only a full ".." segment is forbidden.
        self::assertNull(FilePath::validate('/plugins/foo..bar.yml'));
    }
}
