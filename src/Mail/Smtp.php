<?php

declare(strict_types=1);

namespace Camagru\Mail;

use Camagru\Config;

final class Smtp
{
    /** Validate the environment and build the runtime msmtp configuration. */
    public static function configuration(): string
    {
        $values = [];
        foreach (['SMTP_HOST', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'SMTP_USER', 'SMTP_PASSWORD', 'SMTP_FROM'] as $key) {
            $values[$key] = Config::get($key);
            if (preg_match('/[\x00-\x1f\x7f]/', $values[$key])) {
                throw new \RuntimeException($key . ' must not contain control characters.');
            }
        }
        $host = $values['SMTP_HOST'];
        if ($host === '' || !preg_match('/\A[a-zA-Z0-9._:\[\]-]+\z/', $host)) {
            throw new \RuntimeException('SMTP_HOST must be a hostname or IP address.');
        }
        $port = filter_var($values['SMTP_PORT'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new \RuntimeException('SMTP_PORT must be a port number between 1 and 65535.');
        }
        $encryption = $values['SMTP_ENCRYPTION'];
        if (!in_array($encryption, ['none', 'starttls', 'tls'], true)) {
            throw new \RuntimeException('SMTP_ENCRYPTION must be none, starttls, or tls.');
        }
        $user = $values['SMTP_USER'];
        $password = $values['SMTP_PASSWORD'];
        if (($user === '') !== ($password === '')) {
            throw new \RuntimeException('Set both SMTP_USER and SMTP_PASSWORD, or leave both empty.');
        }
        $from = $values['SMTP_FROM'];
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('SMTP_FROM must be a valid email address.');
        }

        $lines = [
            'defaults',
            'timeout 10',
            'auth ' . ($user === '' ? 'off' : 'on'),
            'tls ' . ($encryption === 'none' ? 'off' : 'on'),
            'tls_starttls ' . ($encryption === 'tls' ? 'off' : 'on'),
            'tls_certcheck on',
            'tls_trust_file /etc/ssl/certs/ca-certificates.crt',
            'account default',
            'host ' . $host,
            'port ' . $port,
            'from "' . $from . '"',
        ];
        if ($user !== '') {
            $lines[] = 'user "' . $user . '"';
            // Read the password directly from the environment without shell interpolation.
            $lines[] = 'passwordeval /usr/bin/printenv SMTP_PASSWORD';
        }
        return implode("\n", $lines) . "\n";
    }
}
