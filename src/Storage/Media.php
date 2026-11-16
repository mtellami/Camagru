<?php

declare(strict_types=1);

namespace Camagru\Storage;

use Camagru\Config;
use Camagru\Database\Database;
use Camagru\Http\HttpError;

/** Local development files or durable PostgreSQL objects for the free deployment. */
final class Media
{
    public static function remote(): bool
    {
        return Config::get('MEDIA_DRIVER', 'local') === 'pgsql';
    }

    public static function upload(string $path): void
    {
        if (self::remote()) {
            Database::query('INSERT INTO media_objects(name,payload) VALUES (?,decode(?,\'base64\'))', [
                basename($path), base64_encode(file_get_contents($path)),
            ]);
        }
    }

    public static function delete(string $name): void
    {
        if (self::remote()) {
            Database::query('DELETE FROM media_objects WHERE name=?', [$name]);
        } else {
            $path = Config::storage() . '/media/' . $name;
            if (is_file($path)) { unlink($path); }
        }
    }

    public static function stream(string $name): never
    {
        $payload = Database::query("SELECT encode(payload,'base64') FROM media_objects WHERE name=?", [$name])->fetchColumn();
        if ($payload === false) { throw new HttpError(404, 'This image is unavailable.'); }
        $bytes = base64_decode($payload, true);
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }
}
