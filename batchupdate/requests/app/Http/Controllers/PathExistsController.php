<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\DaemonError;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\WingsPath;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Requests\PathExistsRequest;

class PathExistsController extends ClientApiController
{
    public function __construct(private readonly DaemonFileRepository $files)
    {
        parent::__construct();
    }

    /**
     * GET /servers/{server}/exists?path=/plugins
     * → { status: "ok", exists: bool, directory: bool } or { status: "error", reason }
     */
    public function __invoke(PathExistsRequest $request, Server $server): JsonResponse
    {
        try {
            $entry = WingsPath::find($this->files->setServer($server), (string) $request->query('path'));
        } catch (DaemonConnectionException $e) {
            return new JsonResponse(['status' => 'error', 'reason' => DaemonError::reason($e)]);
        }

        return new JsonResponse([
            'status' => 'ok',
            'exists' => $entry !== null,
            'directory' => WingsPath::isDirectory($entry),
        ]);
    }
}
