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

    public function handle(Server $server, string $path, string $content): WriteResult
    {
        $result = $this->attempt($server, $path, $content);

        $this->log->log(
            $result->status === WriteResult::OK ? 'info' : 'warning',
            'batchupdate.write',
            ['server_uuid' => $server->uuid, 'path' => $path, 'status' => $result->status, 'reason' => $result->reason]
        );

        return $result;
    }

    private function attempt(Server $server, string $path, string $content): WriteResult
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
            ]);

            return WriteResult::error('unexpected error', 500);
        }
    }

    /** @throws DaemonConnectionException when Wings is unreachable or errors other than 404 */
    private function regularFileExists(DaemonFileRepository $files, string $path): bool
    {
        $dir = dirname($path);
        $name = basename($path);

        try {
            $entries = $files->getDirectory($dir === '' || $dir === '.' ? '/' : $dir);
        } catch (DaemonConnectionException $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }

        foreach ($entries as $entry) {
            if (($entry['name'] ?? null) === $name) {
                return (bool) ($entry['file'] ?? false) && !($entry['directory'] ?? false);
            }
        }

        return false;
    }

    private function fromDaemon(DaemonConnectionException $e): WriteResult
    {
        // 504 is what DaemonConnectionException reports when Guzzle never got a response.
        $status = $e->getStatusCode();
        if ($e->getPrevious() instanceof \GuzzleHttp\Exception\ConnectException || $status === 504) {
            return WriteResult::error('daemon unreachable', 502);
        }

        return WriteResult::error('daemon error: ' . $status, 502);
    }
}
