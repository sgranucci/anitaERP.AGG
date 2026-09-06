<?php

namespace App\Support\Wigos;

use JsonException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Illuminate\Support\php_binary;

/**
 * Ejecuta ODBC/sqlsrv en subproceso con OPENSSL_CONF (SQL Server 2012 + OpenSSL 3).
 * putenv() en el mismo proceso PHP no afecta al driver nativo; el env del hijo sí.
 */
final class WigosSqlServerProcess
{
    /**
     * @return array{version: string}
     */
    public static function consultarVersion(string $alias, int $empresaId = 0): array
    {
        $decoded = self::ejecutar($alias, ['action' => 'version'], $empresaId);

        return ['version' => (string) ($decoded['version'] ?? '')];
    }

    /**
     * @return list<object>
     */
    public static function ejecutarSpVoucherGiftData(string $alias, string $codigo, int $empresaId = 0): array
    {
        $decoded = self::ejecutar($alias, [
            'action' => 'spVoucherGiftData',
            'barcode' => $codigo,
        ], $empresaId);

        $filas = $decoded['rows'] ?? [];
        if (! is_array($filas)) {
            return [];
        }

        return array_map(static fn (array $row) => (object) $row, $filas);
    }

    /**
     * Datos WIGOS por turno (mismos SP que calc_datos_wigos.php / on_line.fc).
     * Usado por Flash caja y por rendición de máquinas (RENDM_lee_on_line).
     *
     * @return array<string, float|int>
     */
    public static function ejecutarCalcDatosFlashTurno(string $fechaYmd, string $turno, int $empresaId = 0): array
    {
        $alias = WigosConfigResolver::currWigos($empresaId);
        $timeoutFlash = (int) config('wigos.flash_process_timeout', 90);
        $fecha = self::normalizarFechaYmd($fechaYmd);
        $turnoNorm = strtoupper(trim($turno));
        try {
            $decoded = self::ejecutar($alias, [
                'action' => 'calcDatosFlashTurno',
                'fecha' => $fecha,
                'turno' => $turnoNorm,
            ], $empresaId, $timeoutFlash);
        } catch (RuntimeException $e) {
            // Timeout de SP flash no reintenta B: suele ser el mismo volumen y B (Biyemas) sin wgdb_000.
            if (! self::esErrorConexionOEspejo($e) || self::esTimeoutEjecucionSubproceso($e)) {
                throw $e;
            }
            $secundario = $alias === 'A' ? 'B' : 'A';
            if (! WigosConfigResolver::conexionConfigurada($secundario, $empresaId)) {
                throw $e;
            }
            $decoded = self::ejecutar($secundario, [
                'action' => 'calcDatosFlashTurno',
                'fecha' => $fecha,
                'turno' => $turnoNorm,
            ], $empresaId, $timeoutFlash);
        }

        $datos = $decoded['datos'] ?? [];
        if (! is_array($datos)) {
            return [];
        }

        return $datos;
    }

    /**
     * Alias semántico para rendición de máquinas (misma action / mismos SP).
     *
     * @return array<string, float|int>
     */
    public static function ejecutarCalcDatosRendicionMaquina(string $fechaYmd, string $turno, int $empresaId = 0): array
    {
        return self::ejecutarCalcDatosFlashTurno($fechaYmd, $turno, $empresaId);
    }

    /**
     * Listados de movimientos Wigos que originan totales del Flash (modal origen).
     *
     * @param  list<string>  $grupos  drop|tickets_venta|tickets_pago|win_egm|qr|sesiones
     * @return array<string, mixed>
     */
    public static function ejecutarDetalleMovimientosFlash(
        string $fechaYmd,
        int $empresaId = 0,
        array $grupos = [],
    ): array {
        $alias = WigosConfigResolver::currWigos($empresaId);
        $timeoutFlash = (int) config('wigos.flash_process_timeout', 90);
        $fecha = self::normalizarFechaYmd($fechaYmd);
        $extra = [
            'action' => 'detalleMovimientosFlash',
            'fecha' => $fecha,
        ];
        if ($grupos !== []) {
            $extra['grupos'] = array_values($grupos);
        }

        try {
            $decoded = self::ejecutar($alias, $extra, $empresaId, $timeoutFlash);
        } catch (RuntimeException $e) {
            if (! self::esErrorConexionOEspejo($e) || self::esTimeoutEjecucionSubproceso($e)) {
                throw $e;
            }
            $secundario = $alias === 'A' ? 'B' : 'A';
            if (! WigosConfigResolver::conexionConfigurada($secundario, $empresaId)) {
                throw $e;
            }
            $decoded = self::ejecutar($secundario, $extra, $empresaId, $timeoutFlash);
        }

        $datos = $decoded['datos'] ?? [];

        return is_array($datos) ? $datos : [];
    }

    private static function normalizarFechaYmd(string $fecha): string
    {
        $fecha = trim($fecha);
        if (preg_match('/^\d{8}$/', $fecha)) {
            return $fecha;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return str_replace('-', '', $fecha);
        }

        throw new RuntimeException('fecha inválida para WIGOS (esperado Ymd o Y-m-d)');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function ejecutar(string $alias, array $extra, int $empresaId = 0, ?int $processTimeout = null): array
    {
        $cfg = WigosConfigResolver::conexion($alias, $empresaId);
        $host = trim((string) ($cfg['host'] ?? ''));
        if ($host === '') {
            throw new RuntimeException('Wigos '.$alias.': conexión no configurada (host vacío).');
        }

        $payload = array_merge([
            'host' => $host,
            'port' => (string) ($cfg['port'] ?? '1433'),
            'database' => (string) ($cfg['database'] ?? 'wgdb_000'),
            'username' => (string) ($cfg['username'] ?? ''),
            'password' => (string) ($cfg['password'] ?? ''),
            'encrypt' => (string) config('wigos.encrypt', 'no'),
            'trust_server_certificate' => (string) config('wigos.trust_server_certificate', 'yes'),
            'login_timeout' => (int) config('wigos.login_timeout', 5),
        ], $extra);

        $script = base_path('scripts/wigos-sqlserver.php');
        if (! is_readable($script)) {
            throw new RuntimeException('Script Wigos SQL no encontrado: '.$script);
        }

        $opensslConf = WigosSqlServerOpenSsl::rutaConfiguracion();
        $env = [
            'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'LANG' => getenv('LANG') ?: 'C.UTF-8',
        ];
        if ($opensslConf !== null) {
            $env['OPENSSL_CONF'] = $opensslConf;
        }

        $timeout = $processTimeout ?? ((int) $payload['login_timeout'] + 15);
        $process = new Process(
            [self::resolverBinarioPhp(), $script, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))],
            base_path(),
            $env,
            null,
            $timeout
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new RuntimeException(
                'Wigos '.$alias.': timeout de ejecución del subproceso SQL ('.$timeout.'s).',
                0,
                $e
            );
        }

        if (! $process->isSuccessful()) {
            $mensaje = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException(
                'Wigos '.$alias.': '.($mensaje !== '' ? $mensaje : 'error en subproceso SQL'),
                (int) $process->getExitCode()
            );
        }

        $stdout = trim($process->getOutput());
        if ($stdout === '') {
            throw new RuntimeException('Wigos '.$alias.': respuesta vacía del subproceso SQL');
        }

        try {
            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(
                'Wigos '.$alias.': respuesta JSON inválida del subproceso SQL',
                0,
                $e
            );
        }

        if (! ($decoded['ok'] ?? false)) {
            throw new RuntimeException('Wigos '.$alias.': subproceso SQL sin éxito');
        }

        return $decoded;
    }

    private static function resolverBinarioPhp(): string
    {
        $configurado = trim((string) config('wigos.php_binary', ''));
        if ($configurado !== '' && is_executable($configurado)) {
            return $configurado;
        }

        if (defined('PHP_BINARY')) {
            $desdeConstante = trim((string) PHP_BINARY);
            if ($desdeConstante !== '' && is_executable($desdeConstante)) {
                return $desdeConstante;
            }
        }

        $encontrado = trim((string) (new PhpExecutableFinder)->find(false));
        if ($encontrado !== '' && is_executable($encontrado)) {
            return $encontrado;
        }

        $candidato = trim((string) php_binary());
        if ($candidato !== '' && ($candidato === 'php' || is_executable($candidato))) {
            return $candidato;
        }

        foreach (['/usr/bin/php8.3', '/usr/bin/php8.2', '/usr/bin/php'] as $ruta) {
            if (is_executable($ruta)) {
                return $ruta;
            }
        }

        throw new RuntimeException(
            'No se encontró el ejecutable PHP para el subproceso Wigos. '
            .'Configure WIGOS_PHP_BINARY=/usr/bin/php8.3 en .env'
        );
    }

    /**
     * Fallback A↔B ante fallo de conexión o base inaccesible en el primario
     * (espejo RESTORING / login a DB), no ante errores de SP/datos.
     */
    public static function esErrorConexionOEspejo(RuntimeException $e): bool
    {
        $mensaje = mb_strtolower($e->getMessage());

        return str_contains($mensaje, 'login timeout expired')
            || str_contains($mensaje, 'no responde (login timeout)')
            || str_contains($mensaje, 'tcp provider')
            || str_contains($mensaje, 'could not connect')
            || str_contains($mensaje, 'connection refused')
            || str_contains($mensaje, 'host vacío')
            || str_contains($mensaje, 'conexión no configurada')
            || str_contains($mensaje, 'cannot open database')
            || str_contains($mensaje, 'the login failed')
            || str_contains($mensaje, 'acting as a mirror database')
            || str_contains($mensaje, 'is restoring');
    }

    private static function esTimeoutEjecucionSubproceso(RuntimeException $e): bool
    {
        return str_contains(mb_strtolower($e->getMessage()), 'timeout de ejecución del subproceso');
    }
}
