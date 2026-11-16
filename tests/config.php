<?php
require dirname(__DIR__) . '/src/bootstrap.php';
function check(bool $ok): void { if (!$ok) { throw new RuntimeException('Assertion failed'); } }
putenv('SMTP_HOST=mailpit'); putenv('SMTP_PORT=1025'); putenv('SMTP_ENCRYPTION=none');
putenv('SMTP_USER='); putenv('SMTP_PASSWORD='); putenv('SMTP_FROM=test@example.com');
check(str_contains(Camagru\Mail\Smtp::configuration(), 'auth off'));
putenv('SMTP_PORT=587'); putenv('SMTP_ENCRYPTION=starttls'); putenv('SMTP_USER=test'); putenv('SMTP_PASSWORD=secret');
check(str_contains(Camagru\Mail\Smtp::configuration(), 'tls_starttls on'));
putenv('SMTP_ENCRYPTION=invalid');
try { Camagru\Mail\Smtp::configuration(); throw new LogicException('Accepted invalid encryption'); } catch (RuntimeException $e) {}
echo "Configuration checks passed.\n";
