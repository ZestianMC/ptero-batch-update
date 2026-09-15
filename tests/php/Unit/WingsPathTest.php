<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\WingsPath;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

final class WingsPathTest extends TestCase
{
    private FakeFiles $files;

    protected function setUp(): void
    {
        $this->files = new FakeFiles();
        $this->files->tree = [
            '/' => [
                ['name' => 'plugins', 'file' => false, 'directory' => true, 'symlink' => false],
                ['name' => 'server.properties', 'file' => true, 'directory' => false, 'symlink' => false],
                ['name' => 'link', 'file' => false, 'directory' => false, 'symlink' => true],
            ],
            '/plugins' => [
                ['name' => 'zCosmetics', 'file' => false, 'directory' => true, 'symlink' => false],
            ],
            '/link' => [
                ['name' => 'inside.txt', 'file' => true, 'directory' => false, 'symlink' => false],
            ],
        ];
    }

    public function testRootIsADirectoryWithoutListing(): void
    {
        $entry = WingsPath::find($this->files, '/');

        self::assertTrue(WingsPath::isDirectory($entry));
        self::assertSame([], $this->files->listedDirs);
    }

    public function testFindsDirectory(): void
    {
        $entry = WingsPath::find($this->files, '/plugins/zCosmetics');

        self::assertTrue(WingsPath::isDirectory($entry));
        self::assertFalse(WingsPath::isRegularFile($entry));
        self::assertSame(['/', '/plugins'], $this->files->listedDirs);
    }

    public function testFindsFile(): void
    {
        $entry = WingsPath::find($this->files, '/server.properties');

        self::assertTrue(WingsPath::isRegularFile($entry));
        self::assertFalse(WingsPath::isDirectory($entry));
    }

    public function testMissingSegmentStopsWalking(): void
    {
        self::assertNull(WingsPath::find($this->files, '/mods/x/y.jar'));
        self::assertSame(['/'], $this->files->listedDirs);
    }

    public function testFileAsIntermediateSegmentIsNotFound(): void
    {
        self::assertNull(WingsPath::find($this->files, '/server.properties/x'));
        self::assertSame(['/'], $this->files->listedDirs);
    }

    public function testFollowsSymlinkedDirectory(): void
    {
        self::assertTrue(WingsPath::isRegularFile(WingsPath::find($this->files, '/link/inside.txt')));
    }

    public function testWings404IsNotFound(): void
    {
        $this->files->onGetDirectory = fn () => throw WriteIfExistsServiceTest::daemonException(404);

        self::assertNull(WingsPath::find($this->files, '/plugins'));
    }

    public function testOtherDaemonErrorsPropagate(): void
    {
        $this->files->onGetDirectory = fn () => throw WriteIfExistsServiceTest::daemonException(500);

        $this->expectException(DaemonConnectionException::class);
        WingsPath::find($this->files, '/plugins');
    }

    public function testPredicatesHandleNull(): void
    {
        self::assertFalse(WingsPath::isRegularFile(null));
        self::assertFalse(WingsPath::isDirectory(null));
    }
}
