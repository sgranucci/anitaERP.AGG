#!/usr/bin/env php
<?php

/**
 * Puente SQL Wigos en subproceso (OPENSSL_CONF del padre vía env, no putenv en el mismo proceso).
 *
 * Uso: php scripts/wigos-sqlserver.php <payload-base64>
 * Payload JSON: action, host, port, database, username, password, encrypt, trust_server_certificate, barcode?
 */
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Falta payload base64\n");
    exit(2);
}

try {
    $payload = json_decode(base64_decode($argv[1], true), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, 'Payload inválido: '.$e->getMessage());
    exit(2);
}

$action = (string) ($payload['action'] ?? '');
$host = trim((string) ($payload['host'] ?? ''));
$port = trim((string) ($payload['port'] ?? '1433'));
$database = (string) ($payload['database'] ?? 'wgdb_000');
$username = (string) ($payload['username'] ?? '');
$password = (string) ($payload['password'] ?? '');
$encrypt = (string) ($payload['encrypt'] ?? 'no');
$trust = (string) ($payload['trust_server_certificate'] ?? 'yes');
$loginTimeout = max(1, (int) ($payload['login_timeout'] ?? 5));
$barcode = trim((string) ($payload['barcode'] ?? ''));

if ($host === '') {
    fwrite(STDERR, 'host vacío');
    exit(2);
}

$dsn = sprintf(
    'sqlsrv:Server=%s,%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=%d',
    $host,
    $port,
    $database,
    $encrypt,
    $trust,
    $loginTimeout
);

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}

try {
    if ($action === 'version') {
        $version = $pdo->query('SELECT @@VERSION AS v')->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'version' => (string) ($version['v'] ?? '')], JSON_THROW_ON_ERROR);

        exit(0);
    }

    if ($action === 'spVoucherGiftData') {
        if ($barcode === '') {
            fwrite(STDERR, 'barcode vacío');
            exit(2);
        }

        $stmt = $pdo->prepare('EXEC spVoucherGiftData @pBarcode = ?');
        $stmt->execute([$barcode]);

        $filas = [];
        do {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $filas[] = $row;
            }
        } while ($stmt->nextRowset());

        echo json_encode(['ok' => true, 'rows' => $filas], JSON_THROW_ON_ERROR);

        exit(0);
    }

    fwrite(STDERR, 'action desconocida: '.$action);
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
