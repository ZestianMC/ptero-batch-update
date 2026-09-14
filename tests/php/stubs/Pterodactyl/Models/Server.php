<?php

namespace Pterodactyl\Models;

/** Test stub. Real class is an Eloquent model; only these attributes are used by the extension. */
class Server
{
    public function __construct(
        public string $uuid = '00000000-0000-0000-0000-000000000000',
        public string $name = 'stub',
        public int $id = 1,
    ) {
    }
}
