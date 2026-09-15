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

/** Scriptable repository: each test sets what getDirectory / putContent do. */
final class FakeFiles extends DaemonFileRepository
{
    /** @var \Closure(string): array */
    public \Closure $onGetDirectory;
    /** @var \Closure(string, string): Response */
    public \Closure $onPutContent;
    public array $putCalls = [];
    public ?Server $lastServer = null;

    public function __construct()
    {
        $this->onGetDirectory = fn (string $p) => [];
        $this->onPutContent = fn (string $p, string $c) => new Response(204);
    }

    public function setServer(Server $server): static
    {
        $this->lastServer = $server;

        return parent::setServer($server);
    }

    public function getDirectory(string $path): array
    {
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

    private static function daemonException(?int $status): DaemonConnectionException
    {
        $req = new Request('GET', 'http://wings');
        $prev = $status === null
            ? new ConnectException('refused', $req)
            : new RequestException('bad', $req, new Response($status, [], '{"error":"nope"}'));

        return new DaemonConnectionException($prev);
    }

    public function testWritesWhenFileExists(): void
    {
        $this->files->onGetDirectory = fn (string $dir) => $dir === '/plugins/zCosmetics/cosmetics'
            ? [self::entry('balloons.yml'), self::entry('hats.yml')]
            : self::fail("unexpected dir $dir");

        $result = $this->service->handle($this->server, '/plugins/zCosmetics/cosmetics/balloons.yml', "a: 1\n", 42);

        self::assertSame('ok', $result->status);
        self::assertSame(200, $result->httpStatus);
        self::assertNull($result->reason);
        self::assertSame([['/plugins/zCosmetics/cosmetics/balloons.yml', "a: 1\n"]], $this->files->putCalls);
        self::assertSame($this->server, $this->files->lastServer);
        self::assertSame(['status' => 'ok'], $result->toArray());
    }

    public function testRootLevelFileUsesRootDirectory(): void
    {
        $seen = null;
        $this->files->onGetDirectory = function (string $dir) use (&$seen) {
            $seen = $dir;

            return [self::entry('server.properties')];
        };

        $result = $this->service->handle($this->server, '/server.properties', 'x', 42);

        self::assertSame('/', $seen);
        self::assertSame('ok', $result->status);
    }

    public function testSkipsWhenNameAbsent(): void
    {
        $this->files->onGetDirectory = fn () => [self::entry('hats.yml')];

        $result = $this->service->handle($this->server, '/plugins/zCosmetics/cosmetics/balloons.yml', 'x', 42);

        self::assertSame('skipped', $result->status);
        self::assertSame('file not found', $result->reason);
        self::assertSame(200, $result->httpStatus);
        self::assertSame([], $this->files->putCalls);
        self::assertSame(['status' => 'skipped', 'reason' => 'file not found'], $result->toArray());
    }

    public function testSkipsWhenNameIsADirectory(): void
    {
        $this->files->onGetDirectory = fn () => [self::entry('balloons.yml', file: false, directory: true)];

        $result = $this->service->handle($this->server, '/plugins/zCosmetics/cosmetics/balloons.yml', 'x', 42);

        self::assertSame('skipped', $result->status);
        self::assertSame([], $this->files->putCalls);
    }

    public function testSkipsWhenNameIsASymlink(): void
    {
        $this->files->onGetDirectory = fn () => [self::entry('balloons.yml', symlink: true)];

        $result = $this->service->handle($this->server, '/plugins/zCosmetics/cosmetics/balloons.yml', 'x', 42);

        self::assertSame('skipped', $result->status);
        self::assertSame('file not found', $result->reason);
        self::assertSame([], $this->files->putCalls);
    }

    public function testSkipsWhenDirectoryMissingOnWings(): void
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
        self::assertSame(502, $result->httpStatus);
    }

    public function testDaemonErrorOnWrite(): void
    {
        $this->files->onGetDirectory = fn () => [self::entry('b.yml')];
        $this->files->onPutContent = fn () => throw self::daemonException(500);

        $result = $this->service->handle($this->server, '/a/b.yml', 'x', 42);

        self::assertSame('error', $result->status);
        self::assertSame('daemon error: 500', $result->reason);
        self::assertSame(502, $result->httpStatus);
    }

    public function testDaemonUnreachableOnWrite(): void
    {
        $this->files->onGetDirectory = fn () => [self::entry('b.yml')];
        $this->files->onPutContent = fn () => throw self::daemonException(null);

        $result = $this->service->handle($this->server, '/a/b.yml', 'x', 42);

        self::assertSame('daemon unreachable', $result->reason);
        self::assertSame(502, $result->httpStatus);
    }

    public function testGenuine504ResponseIsDaemonError(): void
    {
        $this->files->onGetDirectory = fn () => [self::entry('b.yml')];
        $this->files->onPutContent = fn () => throw self::daemonException(504);

        $result = $this->service->handle($this->server, '/a/b.yml', 'x', 42);

        self::assertSame('error', $result->status);
        self::assertSame('daemon error: 504', $result->reason);
        self::assertSame(502, $result->httpStatus);
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
        $this->files->onGetDirectory = fn () => [self::entry('b.yml')];
        $this->service->handle($this->server, '/a/b.yml', 'x', 42);

        $this->files->onGetDirectory = fn () => [];
        $this->service->handle($this->server, '/a/b.yml', 'x', 42);

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
