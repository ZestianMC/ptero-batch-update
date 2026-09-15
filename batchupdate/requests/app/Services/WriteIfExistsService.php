<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Services;

use Psr\Log\LoggerInterface;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\WriteResult;

/**
 * Writes $content to $path on $server only if a regular file already exists there.
 * Never throws: every failure mode is mapped to a WriteResult and logged.
 */
final class WriteIfExistsService
{
    public function __construct(
        private readonly DaemonFileRepository $files,
        private readonly LoggerInterface $log,
    ) {
    }

    public function handle(Server $server, string $path, string $content, int $userId): WriteResult
    {
        $result = $this->attempt($server, $path, $content, $userId);

        $this->log->log(
            $result->status === WriteResult::OK ? 'info' : 'warning',
            'batchupdate.write',
            ['server_uuid' => $server->uuid, 'path' => $path, 'status' => $result->status, 'reason' => $result->reason, 'user_id' => $userId]
        );

        return $result;
    }

    private function attempt(Server $server, string $path, string $content, int $userId): WriteResult
    {
        try {
            $files = $this->files->setServer($server);

            if (!$this->regularFileExists($files, $path)) {
                return WriteResult::skipped('file not found');
            }

            $files->putContent($path, $content);

            return WriteResult::ok();
        } catch (DaemonConnectionException $e) {
            return $this->fromDaemon($e);
        } catch (\Throwable $e) {
            $this->log->error('batchupdate.write_failed', [
                'server_uuid' => $server->uuid,
                'path' => $path,
                'exception' => $e,
                'user_id' => $userId,
            ]);

            return WriteResult::error('unexpected error', 500);
        }
    }

    /**
     * Walks the path from "/" one segment at a time. Listing a directory that does not
     * exist makes Wings answer 404 or, depending on version, a generic 500 that cannot be
     * told apart from a real failure; walking down means we only ever list directories we
     * have just seen, so a missing segment is a clean "not found" and any daemon error is
     * a genuine one.
     *
     * @throws DaemonConnectionException when Wings is unreachable or fails on an existing directory
     */
    private function regularFileExists(DaemonFileRepository $files, string $path): bool
    {
        $segments = array_values(array_filter(explode('/', $path), fn (string $s) => $s !== ''));
        $last = count($segments) - 1;
        $dir = '/';

        foreach ($segments as $i => $segment) {
            $entry = $this->findEntry($files, $dir, $segment);
            if ($entry === null) {
                return false;
            }

            if ($i === $last) {
                return (bool) ($entry['file'] ?? false) && !($entry['directory'] ?? false) && !($entry['symlink'] ?? false);
            }

            if (!($entry['directory'] ?? false) && !($entry['symlink'] ?? false)) {
                return false;
            }

            $dir = rtrim($dir, '/') . '/' . $segment;
        }

        return false;
    }

    /** @throws DaemonConnectionException */
    private function findEntry(DaemonFileRepository $files, string $dir, string $name): ?array
    {
        try {
            $entries = $files->getDirectory($dir);
        } catch (DaemonConnectionException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }

        foreach ($entries as $entry) {
            if (($entry['name'] ?? null) === $name) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Daemon failures are reported with HTTP 200: the request to the panel succeeded, the
     * target did not. Cloudflare (and some proxies) replace origin 502/504 responses with
     * their own error page, which would hide the JSON body from the browser.
     */
    private function fromDaemon(DaemonConnectionException $e): WriteResult
    {
        $prev = $e->getPrevious();
        $hasResponse = $prev !== null && method_exists($prev, 'getResponse') && $prev->getResponse() !== null;
        if (!$hasResponse) {
            return WriteResult::error('daemon unreachable', 200);
        }

        return WriteResult::error('daemon error: ' . $e->getStatusCode(), 200);
    }
}
