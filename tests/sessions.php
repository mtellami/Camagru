<?php
require dirname(__DIR__) . '/src/bootstrap.php';
if (getenv('DB_HOST') !== 'db' || getenv('CI_DISPOSABLE_DB') !== '1') { throw new RuntimeException('Requires disposable database'); }
ini_set('session.use_strict_mode', '1');
session_set_save_handler(new Camagru\Security\DatabaseSession(), true);
session_id(bin2hex(random_bytes(24)));
session_start();
$_SESSION['value'] = 'persisted';
$old = session_id();
session_write_close();
session_id($old); session_start();
if ($_SESSION['value'] !== 'persisted') { throw new RuntimeException('Session data lost'); }
session_regenerate_id(true);
$new = session_id(); session_write_close();
if ($new === $old) { throw new RuntimeException('Session ID not regenerated'); }
session_id($new); session_start();
if ($_SESSION['value'] !== 'persisted') { throw new RuntimeException('Regeneration lost data'); }
session_destroy();
$handler = new Camagru\Security\DatabaseSession();
if ($handler->validateId($old) || $handler->validateId($new)) { throw new RuntimeException('Destroyed session retained'); }
echo "Session lifecycle checks passed.\n";
