<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\FilePath;

/** GET ?path=/some/dir — read-only existence check, so it needs file.read on the target. */
class PathExistsRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_READ;
    }

    public function rules(): array
    {
        return [
            'path' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === '/') {
                        return;
                    }
                    $message = FilePath::validate($value);
                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ],
        ];
    }

    public function validationData(): array
    {
        return $this->query->all();
    }
}
