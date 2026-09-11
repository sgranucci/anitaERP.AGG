<?php

namespace App\Support\Arca;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * CSR para renovar certificados ARCA/AFIP a partir del certificado vigente.
 *
 * No toca cert.crt / privada.key de producción: escribe en un subdirectorio
 * renovacion/{timestamp}/. El alias (CN) y serialNumber=CUIT … se copian
 * del X.509 actual salvo override explícito.
 */
final class ArcaCertificadoCsrSupport
{
    /** @var array<string, string> short OpenSSL name => openssl_csr_new DN key */
    private const DN_MAP = [
        'C' => 'countryName',
        'ST' => 'stateOrProvinceName',
        'L' => 'localityName',
        'O' => 'organizationName',
        'OU' => 'organizationalUnitName',
        'CN' => 'commonName',
        'serialNumber' => 'serialNumber',
        'emailAddress' => 'emailAddress',
    ];

    /** Orden canónico del DN en el CSR (AFIP: C, O, CN, serialNumber). */
    private const DN_ORDEN = [
        'countryName',
        'stateOrProvinceName',
        'localityName',
        'organizationName',
        'organizationalUnitName',
        'commonName',
        'serialNumber',
        'emailAddress',
    ];

    /**
     * @return array{
     *     cert_path: string,
     *     subject: array<string, string>,
     *     alias: string,
     *     cuit: ?string,
     *     serial_number: ?string,
     *     organizacion: ?string,
     *     pais: ?string,
     *     issuer_cn: ?string,
     *     valid_from: ?string,
     *     valid_to: ?string,
     *     valid_from_ts: ?int,
     *     valid_to_ts: ?int,
     *     dias_restantes: ?int,
     *     fingerprint_sha256: string,
     *     dn: array<string, string>
     * }
     */
    public static function leerCertificado(string $certPath): array
    {
        if (! is_readable($certPath)) {
            throw new Exception("No se puede leer el certificado: {$certPath}");
        }

        $pem = (string) file_get_contents($certPath);
        $parsed = @openssl_x509_parse($pem);
        if (! is_array($parsed)) {
            throw new Exception("openssl_x509_parse falló para {$certPath}");
        }

        $subject = self::normalizarSubject($parsed['subject'] ?? []);
        $alias = trim((string) ($subject['CN'] ?? ''));
        $serialNumber = isset($subject['serialNumber']) ? (string) $subject['serialNumber'] : null;
        $cuit = self::cuitDesdeSerial($serialNumber);
        $fromTs = isset($parsed['validFrom_time_t']) ? (int) $parsed['validFrom_time_t'] : null;
        $toTs = isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t'] : null;

        $issuer = is_array($parsed['issuer'] ?? null) ? $parsed['issuer'] : [];
        $issuerCn = isset($issuer['CN']) ? (string) $issuer['CN'] : null;

        return [
            'cert_path' => $certPath,
            'subject' => $subject,
            'alias' => $alias,
            'cuit' => $cuit,
            'serial_number' => $serialNumber,
            'organizacion' => isset($subject['O']) ? (string) $subject['O'] : null,
            'pais' => isset($subject['C']) ? (string) $subject['C'] : null,
            'issuer_cn' => $issuerCn,
            'valid_from' => self::formatearTs($fromTs),
            'valid_to' => self::formatearTs($toTs),
            'valid_from_ts' => $fromTs,
            'valid_to_ts' => $toTs,
            'dias_restantes' => self::diasRestantes($toTs),
            'fingerprint_sha256' => self::fingerprintSha256($pem),
            'dn' => self::dnDesdeSubject($subject),
        ];
    }

    /**
     * @param  array<string, mixed>  $subject
     * @return array<string, string>
     */
    public static function normalizarSubject(array $subject): array
    {
        $out = [];
        foreach ($subject as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            if (is_array($v)) {
                $v = implode(' ', array_map('strval', $v));
            }
            $val = trim((string) $v);
            if ($val !== '') {
                $out[$k] = $val;
            }
        }

        return $out;
    }

    public static function cuitDesdeSerial(?string $serialNumber): ?string
    {
        if ($serialNumber === null || $serialNumber === '') {
            return null;
        }
        if (preg_match('/(\d{11})/', $serialNumber, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @param  array<string, string>  $subject
     * @return array<string, string>
     */
    public static function dnDesdeSubject(array $subject, ?string $aliasOverride = null): array
    {
        $dn = [];
        foreach (self::DN_MAP as $corto => $largo) {
            if (! isset($subject[$corto]) && ! isset($subject[$largo])) {
                continue;
            }
            $valor = (string) ($subject[$corto] ?? $subject[$largo]);
            $valor = trim($valor);
            if ($valor === '') {
                continue;
            }
            $dn[$largo] = $valor;
        }

        $alias = trim((string) $aliasOverride);
        if ($alias !== '') {
            $dn['commonName'] = $alias;
        }

        $ordenado = [];
        foreach (self::DN_ORDEN as $k) {
            if (isset($dn[$k])) {
                $ordenado[$k] = $dn[$k];
            }
        }
        foreach ($dn as $k => $v) {
            if (! isset($ordenado[$k])) {
                $ordenado[$k] = $v;
            }
        }

        if (! isset($ordenado['commonName']) || $ordenado['commonName'] === '') {
            throw new Exception('El certificado no tiene CN (alias ARCA) y no se indicó --alias.');
        }
        if (! isset($ordenado['serialNumber']) || $ordenado['serialNumber'] === '') {
            throw new Exception('El certificado no tiene serialNumber (CUIT …); ARCA lo exige en el CSR.');
        }

        return $ordenado;
    }

    public static function fingerprintSha256(string $pem): string
    {
        return hash('sha256', $pem);
    }

    public static function fingerprintArchivo(string $certPath): ?string
    {
        if (! is_readable($certPath)) {
            return null;
        }

        return self::fingerprintSha256((string) file_get_contents($certPath));
    }

    /**
     * Genera privada.key + pedido.csr. No pisa archivos de producción.
     *
     * @param  array<string, string>  $dn
     * @return array{dir: string, csr_path: string, key_path: string, cnf_path: string, subject: string, alias: string}
     */
    public static function generar(
        string $dirDestino,
        array $dn,
        bool $reutilizarClave = false,
        ?string $claveActualPath = null,
        string $passphrase = '',
    ): array {
        $dirDestino = rtrim($dirDestino, '/');
        if (! is_dir($dirDestino) && ! @mkdir($dirDestino, 0770, true) && ! is_dir($dirDestino)) {
            throw new Exception("No se pudo crear el directorio {$dirDestino}");
        }
        @chmod($dirDestino, 0770);

        $csrPath = $dirDestino.'/pedido.csr';
        $keyPath = $dirDestino.'/privada.key';
        $cnfPath = $dirDestino.'/openssl.cnf';

        if (is_file($csrPath) || is_file($keyPath)) {
            throw new Exception("Ya hay un CSR o clave en {$dirDestino}. Use otro directorio o --force.");
        }

        $configBody = self::construirOpensslCnf($dn);
        if (@file_put_contents($cnfPath, $configBody) === false) {
            throw new Exception("No se pudo escribir {$cnfPath}");
        }

        $configArgs = [
            'config' => $cnfPath,
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'encrypt_key' => false,
        ];

        if ($reutilizarClave) {
            if (! is_string($claveActualPath) || ! is_readable($claveActualPath)) {
                throw new Exception('No se puede reutilizar la clave: privada.key no legible.');
            }
            $priv = openssl_pkey_get_private((string) file_get_contents($claveActualPath), $passphrase);
            if ($priv === false) {
                throw new Exception('No se pudo abrir la clave privada actual: '.self::opensslError());
            }
        } else {
            $priv = openssl_pkey_new($configArgs);
            if ($priv === false) {
                throw new Exception('openssl_pkey_new falló: '.self::opensslError());
            }
        }

        $csr = openssl_csr_new($dn, $priv, $configArgs);
        if ($csr === false) {
            throw new Exception('openssl_csr_new falló: '.self::opensslError());
        }

        if (! openssl_csr_export($csr, $csrOut) || ! is_string($csrOut) || $csrOut === '') {
            throw new Exception('openssl_csr_export falló: '.self::opensslError());
        }
        if (@file_put_contents($csrPath, $csrOut) === false) {
            throw new Exception("No se pudo escribir {$csrPath}");
        }
        @chmod($csrPath, 0644);

        if (! $reutilizarClave) {
            if (! openssl_pkey_export($priv, $keyOut) || ! is_string($keyOut) || $keyOut === '') {
                throw new Exception('openssl_pkey_export falló: '.self::opensslError());
            }
            if (@file_put_contents($keyPath, $keyOut) === false) {
                throw new Exception("No se pudo escribir {$keyPath}");
            }
            @chmod($keyPath, 0600);
        } else {
            if (! @copy($claveActualPath, $keyPath)) {
                throw new Exception("No se pudo copiar la clave actual a {$keyPath}");
            }
            @chmod($keyPath, 0600);
        }

        $subjectCsr = self::subjectDeCsr($csrPath);
        self::assertCsrCoincideDn($dn, $subjectCsr);

        return [
            'dir' => $dirDestino,
            'csr_path' => $csrPath,
            'key_path' => $keyPath,
            'cnf_path' => $cnfPath,
            'subject' => $subjectCsr,
            'alias' => $dn['commonName'],
        ];
    }

    /**
     * @param  array<string, string>  $dn
     */
    public static function construirOpensslCnf(array $dn): string
    {
        $lineas = [
            '[ req ]',
            'default_bits       = 2048',
            'default_md         = sha256',
            'prompt             = no',
            'distinguished_name = req_distinguished_name',
            'string_mask        = utf8only',
            '',
            '[ req_distinguished_name ]',
        ];
        foreach (self::DN_ORDEN as $k) {
            $lineas[] = $k.' = '.self::valorCnf($dn[$k] ?? $k);
        }

        return implode("\n", $lineas)."\n";
    }

    public static function subjectDeCsr(string $csrPath): string
    {
        if (! is_readable($csrPath)) {
            throw new Exception("CSR no legible: {$csrPath}");
        }
        $csr = openssl_csr_get_subject((string) file_get_contents($csrPath), false);
        if (! is_array($csr)) {
            throw new Exception('openssl_csr_get_subject falló: '.self::opensslError());
        }

        return self::formatearSubject($csr);
    }

    /**
     * Acepta PEM con encabezados, Base64 suelto o DER.
     */
    public static function normalizarPemCertificado(string $raw): string
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = trim($raw);
        if ($raw === '') {
            throw new Exception('El archivo del certificado está vacío.');
        }

        if (str_contains($raw, 'BEGIN CERTIFICATE')) {
            if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $raw, $m)) {
                return $m[0]."\n";
            }

            throw new Exception('El PEM no tiene un bloque BEGIN/END CERTIFICATE completo.');
        }

        $x509 = @openssl_x509_read($raw);
        if ($x509 === false) {
            $b64 = preg_replace('/\s+/', '', $raw) ?? '';
            $der = $b64 !== '' ? base64_decode($b64, true) : false;
            if ($der !== false && $der !== '') {
                $x509 = @openssl_x509_read($der);
            }
        }
        if ($x509 === false) {
            throw new Exception('No se pudo interpretar el archivo como certificado X.509 (PEM o DER).');
        }
        if (! openssl_x509_export($x509, $pem) || ! is_string($pem) || $pem === '') {
            throw new Exception('No se pudo exportar el certificado a PEM.');
        }

        return $pem;
    }

    public static function certCoincideConClave(string $certPath, string $keyPath, string $passphrase = ''): bool
    {
        if (! is_readable($certPath) || ! is_readable($keyPath)) {
            return false;
        }
        $cert = openssl_x509_read((string) file_get_contents($certPath));
        $key = openssl_pkey_get_private((string) file_get_contents($keyPath), $passphrase);
        if ($cert === false || $key === false) {
            return false;
        }

        return openssl_x509_check_private_key($cert, $key);
    }

    /**
     * @param  array<string, string>  $dn
     */
    public static function assertCsrCoincideDn(array $dn, string $subjectCsr): void
    {
        $cn = $dn['commonName'] ?? '';
        $serial = $dn['serialNumber'] ?? '';
        if ($cn !== '' && stripos($subjectCsr, $cn) === false) {
            throw new Exception("El CSR no incluye el alias «{$cn}»: {$subjectCsr}");
        }
        if ($serial !== '' && stripos($subjectCsr, $serial) === false) {
            throw new Exception("El CSR no incluye «{$serial}»: {$subjectCsr}");
        }
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    public static function formatearSubject(array $subject): string
    {
        $partes = [];
        $norm = self::normalizarSubject($subject);
        $vistos = [];
        foreach (self::DN_MAP as $corto => $largo) {
            $valor = $norm[$corto] ?? $norm[$largo] ?? null;
            if ($valor === null) {
                continue;
            }
            $partes[] = $corto.' = '.$valor;
            $vistos[$corto] = true;
            $vistos[$largo] = true;
        }
        foreach ($norm as $k => $v) {
            if (! isset($vistos[$k])) {
                $partes[] = $k.' = '.$v;
            }
        }

        return implode(', ', $partes);
    }

    public static function instruccionesArca(string $alias, string $csrPath): string
    {
        $lineas = [
            'Renovación de certificado digital ARCA / AFIP',
            '==============================================',
            '',
            'Alias (CN) a usar (debe ser el mismo que el actual): '.$alias,
            'Archivo CSR: '.$csrPath,
            '',
            '1. Entre a Clave Fiscal (ARCA) con el CUIT titular del certificado.',
            '2. Administración de certificados digitales / Administrador de relaciones.',
            '3. Crear certificado (o renovar) con el MISMO alias: '.$alias,
            '4. Pegue el contenido de pedido.csr (texto PEM que empieza con -----BEGIN CERTIFICATE REQUEST-----).',
            '5. Descargue el certificado emitido (.crt).',
            '6. Guarde ese archivo como cert.crt en esta misma carpeta de renovación',
            '   (junto a la privada.key nueva). No reemplace todavía los de producción.',
            '7. Instale con:',
            '   php artisan arca:generar-csr --instalar --servicio=… --ejecutar',
            '   El comando valida que cert.crt coincida con privada.key, hace backup',
            '   de los archivos vigentes y recién entonces los reemplaza.',
            '',
            'Importante: no pise storage/.../certs/.../privada.key hasta tener el .crt',
            'nuevo. Si lo hace antes, deja de firmar WSAA con el certificado vigente.',
        ];

        return implode("\n", $lineas)."\n";
    }

    private static function valorCnf(string $valor): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $valor);

        return '"'.$escaped.'"';
    }

    private static function formatearTs(?int $ts): ?string
    {
        if ($ts === null || $ts <= 0) {
            return null;
        }
        $dt = (new DateTimeImmutable('@'.$ts))->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));

        return $dt->format('d/m/Y H:i');
    }

    private static function diasRestantes(?int $toTs): ?int
    {
        if ($toTs === null || $toTs <= 0) {
            return null;
        }
        $ahora = time();
        $dias = (int) floor(($toTs - $ahora) / 86400);

        return $dias;
    }

    private static function opensslError(): string
    {
        $bits = [];
        while ($e = openssl_error_string()) {
            $bits[] = $e;
        }

        return $bits !== [] ? implode('; ', $bits) : 'sin detalle OpenSSL';
    }
}
