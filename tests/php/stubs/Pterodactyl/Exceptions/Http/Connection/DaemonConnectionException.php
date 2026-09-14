<?php

namespace Pterodactyl\Exceptions\Http\Connection;

use GuzzleHttp\Exception\GuzzleException;

/** Test stub reproducing the status-code logic of the real panel class. */
class DaemonConnectionException extends \Exception
{
    private int $statusCode = 504;

    public function __construct(GuzzleException $previous, bool $useStatusCode = true)
    {
        $response = method_exists($previous, 'getResponse') ? $previous->getResponse() : null;

        if ($useStatusCode) {
            $this->statusCode = is_null($response) ? $this->statusCode : $response->getStatusCode();
            if ($this->statusCode < 400) {
                $this->statusCode = 502;
            }
        }

        parent::__construct(
            is_null($response) ? 'Could not establish a connection to the machine running this server.' : 'Daemon error',
            0,
            $previous
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
