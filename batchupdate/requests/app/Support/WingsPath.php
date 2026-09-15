<?php

namespace Pterodactyl\BlueprintFramework\Extensions\batchupdate\Support;

use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Resolves a path on a server by walking from "/" one segment at a time.
 *
 * Listing a directory that does not exist makes Wings answer 404 or, depending on version,
 * a generic 500 that cannot be told apart from a real failure. Walking down means we only
 * ever list directories we have just seen, so a missing segment is a clean "not found" and
 * any daemon error is a genuine one.
 */
final class WingsPath
{
    /**
     * Returns the Wings directory entry for $path (keys: name, file, directory, symlink, ...)
     * or null when any segment is missing. The root "/" is reported as a directory entry.
     *
     * @throws DaemonConnectionException when Wings is unreachable or fails on an existing directory
     */
    public static function find(DaemonFileRepository $files, string $path): ?array
    {
        $segments = array_values(array_filter(explode('/', $path), fn (string $s) => $s !== ''));
        if ($segments === []) {
            return ['name' => '/', 'file' => false, 'directory' => true, 'symlink' => false];
        }

        $last = count($segments) - 1;
        $dir = '/';

        foreach ($segments as $i => $segment) {
            $entry = self::findEntry($files, $dir, $segment);
            if ($entry === null) {
                return null;
            }

            if ($i === $last) {
                return $entry;
            }

            if (!($entry['directory'] ?? false) && !($entry['symlink'] ?? false)) {
                return null;
            }

            $dir = rtrim($dir, '/') . '/' . $segment;
        }

        return null;
    }

    public static function isRegularFile(?array $entry): bool
    {
        return $entry !== null
            && (bool) ($entry['file'] ?? false)
            && !($entry['directory'] ?? false)
            && !($entry['symlink'] ?? false);
    }

    public static function isDirectory(?array $entry): bool
    {
        return $entry !== null && (bool) ($entry['directory'] ?? false);
    }

    /** @throws DaemonConnectionException */
    private static function findEntry(DaemonFileRepository $files, string $dir, string $name): ?array
    {
        try {
            $entries = $files->getDirectory($dir);
        } catch (DaemonConnectionException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }

        foreach ($entries as $entry) {
            if (($entry['name'] ?? null) === $name) {
                return $entry;
            }
        }

        return null;
    }
}
