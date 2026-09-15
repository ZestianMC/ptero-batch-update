<?php

namespace Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Services\WriteIfExistsService;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\WriteResult;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

/** Records every log call so tests can assert on the audit trail. */
final class SpyLogger extends AbstractLogger
{
    /** @var list<array{string, string, array}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [(string) $level, (string) $message, $context];
    }
}

/**
 * Fake Wings filesystem. `$tree` maps a directory path to its entries; listing a directory
 * that is not in the tree throws the same 404 the real daemon does. Tests may still override
 * `onGetDirectory` / `onPutContent` to script failures.
 */
final class FakeFiles extends DaemonFileRepository
{
    /** @var array<string, list<array>> */
    public array $tree = [];
    /** @var \Closure(string): array */
    public \Closure $onGetDirectory;
    /** @var \Closure(string, string): Response */
    public \Closure $onPutContent;
    /** @var list<string> */
    public array $listedDirs = [];
    public array $putCalls = [];
    public ?Server $lastServer = null;

    public function __construct()
    {
        $this->onGetDirectory = function (string $dir): array {
            if (!array_key_exists($dir, $this->tree)) {
                throw WriteIfExistsServiceTest::daemonException(404);
            }

            return $this->tree[$dir];
        };
        $this->onPutContent = fn (string $p, string $c) => new Response(204);
    }

    public function setServer(Server $server): static
    {
        $this->lastServer = $server;

        return parent::setServer($server);
    }

    public function getDirectory(string $path): array
    {
        $this->listedDirs[] = $path;

        return ($this->onGetDirectory)($path);
    }

    public function putContent(string $path, string $content): \Psr\Http\Message\ResponseInterface
    {
        $this->putCalls[] = [$path, $content];

        return ($this->onPutContent)($path, $content);
    }
}

final class WriteIfExistsServiceTest extends TestCase
{
    private const PATH = '/plugins/zCosmetics/cosmetics/balloons.yml';

    private FakeFiles $files;
    private SpyLogger $log;
    private WriteIfExistsService $service;
    private Server $server;

    protected function setUp(): void
    {
        $this->files = new FakeFiles();
        $this->log = new SpyLogger();
        $this->service = new WriteIfExistsService($this->files, $this->log);
        $this->server = new Server(uuid: 'aaaaaaaa-0000-0000-0000-000000000000', name: 'lobby');
    }

    private static function entry(string $name, bool $file = true, bool $directory = false, bool $symlink = false): array
    {
        return ['name' => $name, 'file' => $file, 'directory' => $directory, 'symlink' => $symlink, 'size' => 1];
    }

    private static function dir(string $name): array
    {
        return self::entry($name, file: false, directory: true);
    }

    /** A Minecraft-like server that has the target file. */
    private function fullTree(): void
    {
        $this->files->tree = [
            '/' => [self::dir('plugins'), self::entry('server.properties')],
            '/plugins' => [self::dir('zCosmetics'), self::entry('zCosmetics.jar')],
            '/plugins/zCosmetics' => [self::dir('cosmetics'), self::entry('config.yml')],
            '/plugins/zCosmetics/cosmetics' => [self::entry('balloons.yml'), self::entry('hats.yml')],
        ];
    }

    public static function daemonException(?int $status): DaemonConnectionException
    {
        $req = new Request('GET', 'http://wings');
        $prev = $status === null
            ? new ConnectException('refused', $req)
            : new RequestException('bad', $req, new Response($status, [], '{"error":"nope"}'));

        return new DaemonConnectionException($prev);
    }

    public function testWritesWhenFileExists(): void
    {
        $this->fullTree();

        $result = $this->service->handle($this->server, self::PATH, "a: 1\n", 42);

        self::assertSame('ok', $result->status);
        self::assertSame(200, $result->httpStatus);
        self::assertNull($result->reason);
        self::assertSame([[self::PATH, "a: 1\n"]], $this->files->putCalls);
        self::assertSame($this->server, $this->files->lastServer);
        self::assertSame(['status' => 'ok'], $result->toArray());
    }

    public function testWalksEveryDirectoryFromRoot(): void
    {
        $this->fullTree();

        $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame(['/', '/plugins', '/plugins/zCosmetics', '/plugins/zCosmetics/cosmetics'], $this->files->listedDirs);
    }

    public function testRootLevelFileUsesRootDirectory(): void
    {
        $this->files->tree = ['/' => [self::entry('server.properties')]];

        $result = $this->service->handle($this->server, '/server.properties', 'x', 42);

        self::assertSame(['/'], $this->files->listedDirs);
        self::assertSame('ok', $result->status);
    }

    public function testSkipsWhenNameAbsent(): void
    {
        $this->fullTree();
        $this->files->tree['/plugins/zCosmetics/cosmetics'] = [self::entry('hats.yml')];

        $result = $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame('skipped', $result->status);
        self::assertSame('file not found', $result->reason);
        self::assertSame(200, $result->httpStatus);
        self::assertSame([], $this->files->putCalls);
        self::assertSame(['status' => 'skipped', 'reason' => 'file not found'], $result->toArray());
    }

    public function testSkipsWhenNameIsADirectory(): void
    {
        $this->fullTree();
        $this->files->tree['/plugins/zCosmetics/cosmetics'] = [self::dir('balloons.yml')];

        $result = $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame('skipped', $result->status);
        self::assertSame([], $this->files->putCalls);
    }

    public function testSkipsWhenNameIsASymlink(): void
    {
        $this->fullTree();
        $this->files->tree['/plugins/zCosmetics/cosmetics'] = [self::entry('balloons.yml', symlink: true)];

        $result = $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame('skipped', $result->status);
        self::assertSame('file not found', $result->reason);
        self::assertSame([], $this->files->putCalls);
    }

    /** A Discord bot / proxy server: no /plugins at all. Must never list a missing directory. */
    public function testSkipsWhenIntermediateDirectoryIsMissingWithoutListingIt(): void
    {
        $this->files->tree = ['/' => [self::entry('bot.js'), self::dir('node_modules')]];

        $result = $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame('skipped', $result->status);
        self::assertSame('file not found', $result->reason);
        self::assertSame(['/'], $this->files->listedDirs);
        self::assertSame([], $this->files->putCalls);
    }

    public function testSkipsWhenIntermediateSegmentIsAFile(): void
    {
        $this->files->tree = ['/' => [self::entry('plugins')]];

        $result = $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame('skipped', $result->status);
        self::assertSame(['/'], $this->files->listedDirs);
    }

    public function testFollowsSymlinkedIntermediateDirectory(): void
    {
        $this->fullTree();
        $this->files->tree['/'] = [self::entry('plugins', file: false, symlink: true)];

        $result = $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame('ok', $result->status);
    }

    public function testSkipsWhenWingsAnswers404ForADirectory(): void
    {
        $this->files->onGetDirectory = fn () => throw self::daemonException(404);

        $result = $this->service->handle($this->server, '/plugins/missing/x.yml', 'x', 42);

        self::assertSame('skipped', $result->status);
        self::assertSame('file not found', $result->reason);
        self::assertSame([], $this->files->putCalls);
    }

    public function testDaemonUnreachableOnListing(): void
    {
        $this->files->onGetDirectory = fn () => throw self::daemonException(null);

        $result = $this->service->handle($this->server, '/a/b.yml', 'x', 42);

        self::assertSame('error', $result->status);
        self::assertSame('daemon unreachable', $result->reason);
        self::assertSame(200, $result->httpStatus);
    }

    public function testDaemonErrorOnExistingDirectoryIsReported(): void
    {
        $this->files->onGetDirectory = fn () => throw self::daemonException(500);

        $result = $this->service->handle($this->server, '/a/b.yml', 'x', 42);

        self::assertSame('error', $result->status);
        self::assertSame('daemon error: 500', $result->reason);
        self::assertSame(200, $result->httpStatus);
    }

    public function testDaemonErrorOnWrite(): void
    {
        $this->fullTree();
        $this->files->onPutContent = fn () => throw self::daemonException(500);

        $result = $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame('error', $result->status);
        self::assertSame('daemon error: 500', $result->reason);
        self::assertSame(200, $result->httpStatus);
    }

    public function testDaemonUnreachableOnWrite(): void
    {
        $this->fullTree();
        $this->files->onPutContent = fn () => throw self::daemonException(null);

        $result = $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame('daemon unreachable', $result->reason);
        self::assertSame(200, $result->httpStatus);
    }

    public function testGenuine504ResponseIsDaemonError(): void
    {
        $this->fullTree();
        $this->files->onPutContent = fn () => throw self::daemonException(504);

        $result = $this->service->handle($this->server, self::PATH, 'x', 42);

        self::assertSame('error', $result->status);
        self::assertSame('daemon error: 504', $result->reason);
        self::assertSame(200, $result->httpStatus);
    }

    public function testUnexpectedThrowableIsContainedAndLogged(): void
    {
        $this->files->onGetDirectory = fn () => throw new \RuntimeException('boom');

        $result = $this->service->handle($this->server, '/a/b.yml', 'x', 42);

        self::assertSame('error', $result->status);
        self::assertSame('unexpected error', $result->reason);
        self::assertSame(500, $result->httpStatus);

        $errors = array_filter($this->log->records, fn ($r) => $r[0] === 'error');
        self::assertCount(1, $errors);
        $record = array_values($errors)[0];
        self::assertSame('batchupdate.write_failed', $record[1]);
        self::assertSame('aaaaaaaa-0000-0000-0000-000000000000', $record[2]['server_uuid']);
        self::assertSame('/a/b.yml', $record[2]['path']);
        self::assertInstanceOf(\RuntimeException::class, $record[2]['exception']);
        self::assertSame(42, $record[2]['user_id']);
    }

    public function testEveryOutcomeIsLoggedWithStatus(): void
    {
        $this->fullTree();
        $this->service->handle($this->server, self::PATH, 'x', 42);

        $this->files->tree['/plugins/zCosmetics/cosmetics'] = [];
        $this->service->handle($this->server, self::PATH, 'x', 42);

        $statuses = array_map(fn ($r) => [$r[0], $r[2]['status'] ?? null], $this->log->records);
        self::assertContains(['info', 'ok'], $statuses);
        self::assertContains(['warning', 'skipped'], $statuses);

        foreach ($this->log->records as $record) {
            self::assertSame(42, $record[2]['user_id'] ?? null);
        }
    }

    public function testWriteResultToArrayOmitsNullReason(): void
    {
        self::assertSame(['status' => 'ok'], WriteResult::ok()->toArray());
        self::assertSame(['status' => 'error', 'reason' => 'x'], WriteResult::error('x', 500)->toArray());
    }
}
