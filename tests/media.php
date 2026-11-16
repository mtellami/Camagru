<?php
putenv('MEDIA_DRIVER=pgsql');
$name = bin2hex(random_bytes(16)) . '.jpg';
$path = sys_get_temp_dir() . '/' . $name;
$bytes = "\x00\xffbinary-image\x00";
file_put_contents($path, $bytes);
try {
    \Camagru\Storage\Media::upload($path);
    $stored = \Camagru\Database\Database::query("SELECT encode(payload,'base64') FROM media_objects WHERE name=?", [$name])->fetchColumn();
    if (base64_decode($stored) !== $bytes) { throw new RuntimeException('Media bytes changed'); }
    \Camagru\Storage\Media::delete($name);
    \Camagru\Storage\Media::delete($name);
    if (\Camagru\Database\Database::query('SELECT 1 FROM media_objects WHERE name=?', [$name])->fetchColumn()) { throw new RuntimeException('Media deletion failed'); }
} finally { unlink($path); putenv('MEDIA_DRIVER=local'); }
echo "Durable media checks passed.\n";
