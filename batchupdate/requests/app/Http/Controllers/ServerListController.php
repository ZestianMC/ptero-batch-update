<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class ServerListController extends ClientApiController
{
    /**
     * GET /servers — every server the user may write files on.
     * Root admins see the whole panel; others see accessible servers with file.update.
     */
    public function __invoke(ClientApiRequest $request): JsonResponse
    {
        $user = $request->user();

        $servers = $user->root_admin
            ? Server::query()->orderBy('name')->get()
            : $user->accessibleServers()->orderBy('name')->get()
                ->filter(fn (Server $server) => $user->can(Permission::ACTION_FILE_UPDATE, $server))
                ->values();

        return new JsonResponse([
            'data' => $servers->map(fn (Server $server) => [
                'uuid' => $server->uuid,
                'name' => $server->name,
            ])->all(),
        ]);
    }
}
