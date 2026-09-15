<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support;

use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

final class DaemonError
{
    /** Short, user-facing reason for a Wings failure. */
    public static function reason(DaemonConnectionException $e): string
    {
        $prev = $e->getPrevious();
        $hasResponse = $prev !== null && method_exists($prev, 'getResponse') && $prev->getResponse() !== null;

        return $hasResponse ? 'daemon error: ' . $e->getStatusCode() : 'daemon unreachable';
    }
}
