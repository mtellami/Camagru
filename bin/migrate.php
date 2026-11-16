<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
use Camagru\Database\Database;
$db = Database::connection();
$db->beginTransaction();
try {
    $db->exec('SELECT pg_advisory_xact_lock(4298044)');
    if (!$db->query("SELECT to_regclass('public.schema_migrations')")->fetchColumn()) {
        $tables = $db->query("SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'")->fetchColumn();
        if ((int) $tables !== 0) { throw new RuntimeException('Existing unversioned schema: review and baseline manually; no changes applied.'); }
        $db->exec(file_get_contents(dirname(__DIR__) . '/src/Database/schema.sql'));
        $db->exec('CREATE TABLE schema_migrations(version INTEGER PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');
        $db->exec('INSERT INTO schema_migrations(version) VALUES (1)');
    }
    if (!$db->query('SELECT 1 FROM schema_migrations WHERE version=2')->fetchColumn()) {
        $db->exec('CREATE TABLE sessions(id CHAR(64) PRIMARY KEY,payload TEXT NOT NULL,expires_at TIMESTAMPTZ NOT NULL)');
        $db->exec('CREATE INDEX sessions_expiry ON sessions(expires_at)');
        $db->exec('INSERT INTO schema_migrations(version) VALUES (2)');
    }
    if (!$db->query('SELECT 1 FROM schema_migrations WHERE version=3')->fetchColumn()) {
        $db->exec('CREATE TABLE media_objects(name VARCHAR(64) PRIMARY KEY,payload BYTEA NOT NULL)');
        $db->exec('INSERT INTO schema_migrations(version) VALUES (3)');
    }
    $db->commit();
    echo "Migrations complete.\n";
} catch (Throwable $e) { $db->rollBack(); fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
