<?php
require dirname(__DIR__) . '/src/bootstrap.php';
use Camagru\Database\Database;
// Run only against the disposable CI database, never Neon.
if (getenv('DB_HOST') !== 'db' || getenv('CI_DISPOSABLE_DB') !== '1') { throw new RuntimeException('Requires disposable db service'); }
$db = Database::connection();
$db->exec('CREATE TABLE sessions(id CHAR(64) PRIMARY KEY,payload TEXT NOT NULL,expires_at TIMESTAMPTZ NOT NULL)');
$handler = new Camagru\Security\DatabaseSession();
$handler->open('', 'test');
$id = bin2hex(random_bytes(32));
if ($handler->read($id) !== '') { throw new RuntimeException('Unexpected session'); }
$handler->write($id, 'test|s:5:"value";'); $handler->close();
if (!$handler->validateId($id) || $handler->read($id) !== 'test|s:5:"value";') { throw new RuntimeException('Session did not persist'); }
$handler->destroy($id); $handler->close();
if ($handler->validateId($id)) { throw new RuntimeException('Destroyed session remains'); }
$db->exec('DROP TABLE sessions');
// Migration refuses preexisting unversioned data.
exec('php bin/migrate.php 2>/dev/null', $output, $status);
if ($status !== 1) { throw new RuntimeException('Unsafe migration accepted'); }
$db->exec('DROP SCHEMA public CASCADE'); $db->exec('CREATE SCHEMA public');
passthru('php bin/migrate.php', $status); if ($status) { exit($status); }
passthru('php bin/migrate.php', $status); if ($status) { exit($status); }
require __DIR__ . '/media.php';
