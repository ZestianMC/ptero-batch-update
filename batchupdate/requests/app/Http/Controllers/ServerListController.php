<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class ServerListController extends ClientApiController
{
    /** Permissions a caller may filter by; anything else falls back to file.update. */
    private const ALLOWED = [Permission::ACTION_FILE_UPDATE, Permission::ACTION_FILE_CREATE];

    /**
     * GET /servers?permission=file.update|file.create — every server the user holds that
     * permission on. Root admins see the whole panel.
     */
    public function __invoke(ClientApiRequest $request): JsonResponse
    {
        $user = $request->user();
        $permission = $request->query('permission');
        if (!in_array($permission, self::ALLOWED, true)) {
            $permission = Permission::ACTION_FILE_UPDATE;
        }

        $servers = $user->root_admin
            ? Server::query()->select(['id', 'uuid', 'name', 'owner_id'])->orderBy('name')->get()
            : $user->accessibleServers()->with('subusers')->orderBy('name')->get()
                ->filter(fn (Server $server) => $user->can($permission, $server))
                ->values();

        return new JsonResponse([
            'data' => $servers->map(fn (Server $server) => [
                'uuid' => $server->uuid,
                'name' => $server->name,
            ])->all(),
        ]);
    }
}
