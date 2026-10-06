<?php

namespace App\Support\Ventas\Emita;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Conexión de solo lectura al SQL Server de Emita (clientes VIP).
 */
final class EmitaSqlServer
{
    public static function conectar(): PDO
    {
        $host = trim((string) config('emita.sql.host'));
        $database = trim((string) config('emita.sql.database'));
        $username = (string) config('emita.sql.username');
        $password = (string) config('emita.sql.password');

        if ($host === '' || $database === '' || $username === '' || $password === '') {
            throw new RuntimeException('La conexión a Emita no está configurada.');
        }

        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=%d',
            $host,
            $database,
            (string) config('emita.sql.encrypt', 'no'),
            (string) config('emita.sql.trust_server_certificate', 'yes'),
            max(1, (int) config('emita.sql.login_timeout', 12))
        );

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('No se pudo conectar con la base de clientes VIP de Emita.');
        }
    }
}
