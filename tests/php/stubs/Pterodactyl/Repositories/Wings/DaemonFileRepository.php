<?php

namespace Pterodactyl\Repositories\Wings;

use Pterodactyl\Models\Server;
use Psr\Http\Message\ResponseInterface;

/** Test stub mirroring the public surface the extension uses. Tests subclass or mock it. */
class DaemonFileRepository
{
    protected ?Server $server = null;

    public function setServer(Server $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function getDirectory(string $path): array
    {
        throw new \LogicException('stub: override in test');
    }

    public function putContent(string $path, string $content): ResponseInterface
    {
        throw new \LogicException('stub: override in test');
    }
}
