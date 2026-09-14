<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support;

/**
 * Framework-free validation for the target file path. Returns null when the
 * path is acceptable, otherwise a short message suitable for a 422 response.
 */
final class FilePath
{
    public const MAX_LENGTH = 4096;

    public static function validate(mixed $path): ?string
    {
        if (!is_string($path) || $path === '') {
            return 'The file path is required.';
        }
        if (strlen($path) > self::MAX_LENGTH) {
            return 'The file path is too long.';
        }
        if (str_contains($path, "\0")) {
            return 'The file path contains invalid characters.';
        }
        if (!str_starts_with($path, '/')) {
            return 'The file path must start with /.';
        }
        if (str_ends_with($path, '/')) {
            return 'The file path must not end with /.';
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                return 'The file path must not contain .. segments.';
            }
        }

        return null;
    }
}
