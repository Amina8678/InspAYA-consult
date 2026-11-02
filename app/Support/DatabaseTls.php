<?php

namespace App\Support;

use Illuminate\Support\ConfigurationUrlParser;

/**
 * Whether a database connection config guarantees an encrypted connection.
 * Used by `launch:check`: a production database reached over the internet
 * (Neon) must never fall back to plaintext.
 *
 * Connections to this machine (loopback or a unix socket) are exempt: there
 * is no network to protect.
 */
class DatabaseTls
{
    /** PostgreSQL sslmode values under which libpq refuses a plaintext connection. */
    private const PGSQL_ENCRYPTED_MODES = ['require', 'verify-ca', 'verify-full'];

    /**
     * Value of Pdo\Mysql::ATTR_SSL_CA (PDO::MYSQL_ATTR_SSL_CA is deprecated
     * since PHP 8.5). A literal, so this works without pdo_mysql loaded.
     */
    private const MYSQL_ATTR_SSL_CA = 1009;

    /** @param array<string, mixed> $config a config/database.php connection entry */
    public static function problem(array $config): ?string
    {
        // Laravel resolves DB_URL at connect time; do the same here so
        // `?sslmode=require` in a URL counts.
        $config = (new ConfigurationUrlParser)->parseConfiguration($config);

        $driver = $config['driver'] ?? null;
        if (! in_array($driver, ['pgsql', 'mysql', 'mariadb'], true) || self::isLocal($config)) {
            return null;
        }

        if ($driver === 'pgsql') {
            $mode = (string) ($config['sslmode'] ?? 'prefer');

            return in_array($mode, self::PGSQL_ENCRYPTED_MODES, true)
                ? null
                : 'DB_SSLMODE is "'.$mode.'", which can fall back to an unencrypted database connection. Set it to require (or verify-full).';
        }

        return blank($config['options'][self::MYSQL_ATTR_SSL_CA] ?? null)
            ? 'The MySQL connection has no TLS settings (MYSQL_ATTR_SSL_CA). Encrypt the connection to a remote database.'
            : null;
    }

    /** @param array<string, mixed> $config */
    private static function isLocal(array $config): bool
    {
        $host = (string) ($config['host'] ?? '');
        if ($host === '' && ! empty($config['unix_socket'])) {
            return true;
        }

        return in_array($host, ['127.0.0.1', '::1', 'localhost'], true) || str_starts_with($host, '/');
    }
}
