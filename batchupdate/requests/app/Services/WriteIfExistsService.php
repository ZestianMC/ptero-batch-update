<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Services;

use Psr\Log\LoggerInterface;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\DaemonError;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\WingsPath;
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

            if (!WingsPath::isRegularFile(WingsPath::find($files, $path))) {
                return WriteResult::skipped('file not found');
            }

            $files->putContent($path, $content);

            return WriteResult::ok();
        } catch (DaemonConnectionException $e) {
            // HTTP 200: the request to the panel succeeded, the target did not. Cloudflare and
            // some proxies replace origin 502/504 responses with their own error page, which
            // would hide the JSON body from the browser.
            return WriteResult::error(DaemonError::reason($e), 200);
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
}
