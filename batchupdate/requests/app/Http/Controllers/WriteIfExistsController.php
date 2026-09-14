<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Services\WriteIfExistsService;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Requests\WriteIfExistsRequest;

class WriteIfExistsController extends ClientApiController
{
    public function __construct(private readonly DaemonFileRepository $files)
    {
        parent::__construct();
    }

    /**
     * POST /servers/{server}/write-if-exists?file=/path
     * Body: raw file content (Content-Type: text/plain).
     */
    public function __invoke(WriteIfExistsRequest $request, Server $server): JsonResponse
    {
        $service = new WriteIfExistsService($this->files, Log::channel());

        $result = $service->handle(
            $server,
            (string) $request->query('file'),
            (string) $request->getContent(),
        );

        return new JsonResponse($result->toArray(), $result->httpStatus);
    }
}
