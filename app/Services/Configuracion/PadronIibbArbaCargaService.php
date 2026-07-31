<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Carga masiva ARBA (Percepción + Retención) en padron_iibb_arba.
 *
 * Zip o TXT: Per inserta; Ret actualiza por cuit+vigencia o inserta si no hay fila.
 */
class PadronIibbArbaCargaService
{
    public const DEFAULT_BATCH = 5000;

    public const DEFAULT_PAUSE_MS = 20;

    /**
     * @return array{archivos:list<array{tipo:string,archivo:string,leidas:int,insertadas:int,actualizadas:int,errores:int}>}
     */
    public function cargar(
        string $entrada,
        int $batchSize = self::DEFAULT_BATCH,
        int $pauseMs = self::DEFAULT_PAUSE_MS
    ): array {
        $batchSize = max(100, $batchSize);
        $pauseMs = max(0, $pauseMs);

        $archivos = $this->resolverArchivosPadron($entrada);
        if ($archivos === []) {
            throw new RuntimeException('No se encontraron archivos Per/Ret para procesar.');
        }

        @ini_set('memory_limit', '128M');
        gc_enable();

        $pdo = DB::connection()->getPdo();
        $this->prepararSesion($pdo);

        $sqlInsertPer = 'INSERT INTO padron_iibb_arba
            (cuit, desdefecha, hastafecha, tasapercepcion, tipocontribuyente)
            VALUES (:cuit, :desdefecha, :hastafecha, :tasapercepcion, :tipocontribuyente)';
        $sqlInsertRet = 'INSERT INTO padron_iibb_arba
            (cuit, desdefecha, hastafecha, tasaretencion, tipocontribuyente)
            VALUES (:cuit, :desdefecha, :hastafecha, :tasaretencion, :tipocontribuyente)';
        $sqlUpdateRet = 'UPDATE padron_iibb_arba
            SET tasaretencion = :tasaretencion,
                tipocontribuyente = COALESCE(NULLIF(:tipocontribuyente, \'\'), tipocontribuyente)
            WHERE cuit = :cuit AND desdefecha = :desdefecha AND hastafecha = :hastafecha';

        $stmtInsertPer = $pdo->prepare($sqlInsertPer);
        $stmtInsertRet = $pdo->prepare($sqlInsertRet);
        $stmtUpdateRet = $pdo->prepare($sqlUpdateRet);

        $resultado = ['archivos' => []];

        Log::info('padron_iibb_arba:carga:inicio', ['entrada' => $entrada, 'archivos' => $archivos]);

        try {
            foreach ($archivos as $archivo) {
                $tipo = $this->detectarTipoPadron($archivo);
                if ($tipo === null) {
                    Log::warning('padron_iibb_arba:carga:omitido', ['archivo' => $archivo]);
                    continue;
                }

                $stats = $this->procesarArchivo(
                    $pdo,
                    $archivo,
                    $tipo,
                    $stmtInsertPer,
                    $stmtInsertRet,
                    $stmtUpdateRet,
                    $batchSize,
                    $pauseMs
                );
                $stats['tipo'] = $tipo;
                $stats['archivo'] = $archivo;
                $resultado['archivos'][] = $stats;

                Log::info('padron_iibb_arba:carga:archivo_ok', $stats);
            }
        } finally {
            $this->restaurarSesion($pdo);
        }

        Log::info('padron_iibb_arba:carga:fin', $resultado);

        return $resultado;
    }

    /**
     * @return list<string>
     */
    public function resolverArchivosPadron(string $entrada): array
    {
        if (! is_file($entrada)) {
            throw new RuntimeException("No existe: {$entrada}");
        }

        $ext = strtolower(pathinfo($entrada, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return [$entrada];
        }

        $destDir = dirname($entrada);
        $zip = new ZipArchive;
        if ($zip->open($entrada) !== true) {
            throw new RuntimeException("Error al abrir zip: {$entrada}");
        }

        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = basename($zip->statIndex($i)['name']);
        }
        $zip->extractTo($destDir);
        $zip->close();

        $out = [];
        foreach ($nombres as $nombre) {
            if ($this->detectarTipoPadron($nombre) === null) {
                continue;
            }
            $out[] = $destDir . DIRECTORY_SEPARATOR . $nombre;
        }

        usort($out, function (string $a, string $b): int {
            $ta = $this->detectarTipoPadron($a) === 'P' ? 0 : 1;
            $tb = $this->detectarTipoPadron($b) === 'P' ? 0 : 1;

            return $ta <=> $tb;
        });

        return $out;
    }

    public function detectarTipoPadron(string $pathOrName): ?string
    {
        $base = strtoupper(basename($pathOrName));
        if (str_contains($base, 'PADRONRGSPER') || str_contains($base, 'RGSPER')) {
            return 'P';
        }
        if (str_contains($base, 'PADRONRGSRET') || str_contains($base, 'RGSRET')) {
            return 'R';
        }

        return null;
    }

    /**
     * @return array{leidas:int,insertadas:int,actualizadas:int,errores:int}
     */
    private function procesarArchivo(
        \PDO $pdo,
        string $archivo,
        string $tipo,
        \PDOStatement $stmtInsertPer,
        \PDOStatement $stmtInsertRet,
        \PDOStatement $stmtUpdateRet,
        int $batchSize,
        int $pauseMs
    ): array {
        $stats = ['leidas' => 0, 'insertadas' => 0, 'actualizadas' => 0, 'errores' => 0];

        $handle = fopen($archivo, 'r');
        if ($handle === false) {
            $stats['errores']++;

            return $stats;
        }

        $batch = 0;
        $pdo->beginTransaction();

        try {
            while (($columnas = fgetcsv($handle, 0, ';')) !== false) {
                if (! is_array($columnas) || count($columnas) < 9) {
                    continue;
                }

                $marca = strtoupper(trim((string) $columnas[0]));
                $tipoLinea = ($marca === 'P' || $marca === 'R') ? $marca : $tipo;

                $desdeFecha = DateTime::createFromFormat('dmY', trim((string) $columnas[2]));
                $hastaFecha = DateTime::createFromFormat('dmY', trim((string) $columnas[3]));
                if (! $desdeFecha || ! $hastaFecha) {
                    $stats['errores']++;
                    continue;
                }

                $cuit = preg_replace('/\D+/', '', (string) $columnas[4]) ?? '';
                $tasa = (float) str_replace(',', '.', trim((string) $columnas[8]));
                $tipoContrib = trim((string) ($columnas[5] ?? ''));

                $stats['leidas']++;

                if ($tipoLinea === 'P') {
                    $stmtInsertPer->execute([
                        ':cuit' => $cuit,
                        ':desdefecha' => $desdeFecha->format('Y-m-d'),
                        ':hastafecha' => $hastaFecha->format('Y-m-d'),
                        ':tasapercepcion' => $tasa,
                        ':tipocontribuyente' => $tipoContrib,
                    ]);
                    $stats['insertadas']++;
                } else {
                    $params = [
                        ':cuit' => $cuit,
                        ':desdefecha' => $desdeFecha->format('Y-m-d'),
                        ':hastafecha' => $hastaFecha->format('Y-m-d'),
                        ':tasaretencion' => $tasa,
                        ':tipocontribuyente' => $tipoContrib,
                    ];
                    $stmtUpdateRet->execute($params);
                    if ($stmtUpdateRet->rowCount() > 0) {
                        $stats['actualizadas']++;
                    } else {
                        $stmtInsertRet->execute([
                            ':cuit' => $cuit,
                            ':desdefecha' => $desdeFecha->format('Y-m-d'),
                            ':hastafecha' => $hastaFecha->format('Y-m-d'),
                            ':tasaretencion' => $tasa,
                            ':tipocontribuyente' => $tipoContrib,
                        ]);
                        $stats['insertadas']++;
                    }
                }

                $batch++;
                if ($batch >= $batchSize) {
                    $pdo->commit();
                    if ($pauseMs > 0) {
                        usleep($pauseMs * 1000);
                    }
                    $pdo->beginTransaction();
                    $batch = 0;
                    if ($stats['leidas'] % ($batchSize * 25) === 0) {
                        Log::info('padron_iibb_arba:carga:progreso', [
                            'tipo' => $tipo,
                            'leidas' => $stats['leidas'],
                            'insertadas' => $stats['insertadas'],
                            'actualizadas' => $stats['actualizadas'],
                        ]);
                        gc_collect_cycles();
                    }
                }
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Log::error('padron_iibb_arba:carga:error', [
                'archivo' => $archivo,
                'error' => $e->getMessage(),
            ]);
            $stats['errores']++;
            throw $e;
        } finally {
            fclose($handle);
        }

        return $stats;
    }

    private function prepararSesion(\PDO $pdo): void
    {
        try {
            $pdo->exec('SET SESSION innodb_lock_wait_timeout = 30');
            $pdo->exec('SET SESSION unique_checks = 0');
            $pdo->exec('SET SESSION foreign_key_checks = 0');
        } catch (Throwable $e) {
            // ignore
        }
    }

    private function restaurarSesion(\PDO $pdo): void
    {
        try {
            $pdo->exec('SET SESSION unique_checks = 1');
            $pdo->exec('SET SESSION foreign_key_checks = 1');
        } catch (Throwable $e) {
            // ignore
        }
    }
}
