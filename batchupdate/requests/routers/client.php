<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Controllers\ServerListController;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Controllers\WriteIfExistsController;

// Mounted by Blueprint at /api/client/extensions/batchupdate with the
// blueprint/api + blueprint/client-api middleware groups (auth, bindings, throttle).

Route::get('/servers', ServerListController::class);

Route::prefix('/servers/{server}')
    ->middleware([AuthenticateServerAccess::class])
    ->group(function () {
        Route::post('/write-if-exists', WriteIfExistsController::class);
    });
