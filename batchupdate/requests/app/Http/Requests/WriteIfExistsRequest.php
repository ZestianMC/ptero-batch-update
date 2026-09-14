<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support\FilePath;

/**
 * Authorises via ClientApiRequest::authorize() -> $user->can('file.update', $server).
 * The file content is the raw request body (see WriteIfExistsController), so the
 * only validated input is the `file` query parameter.
 */
class WriteIfExistsRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_UPDATE;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $message = FilePath::validate($value);
                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ],
        ];
    }

    /** Validate the query string, not the (raw) body. */
    public function validationData(): array
    {
        return $this->query->all();
    }
}
