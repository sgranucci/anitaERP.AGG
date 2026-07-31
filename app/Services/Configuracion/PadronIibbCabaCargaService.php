<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Carga masiva AGIP (CABA) en padron_iibb_caba: stream + INSERT por lotes.
 */
class PadronIibbCabaCargaService
{
    public const DEFAULT_BATCH = 2000;

    public const DEFAULT_PAUSE_MS = 20;

    private const PROGRESS_EVERY_BATCHES = 25;

    /**
     * @return array{leidas:int,insertadas:int,omitidas:int,errores:int,lotes:int,borrados:int,desdefecha:?string,hastafecha:?string}
     */
    public function cargar(
        string $archivo,
        int $batchSize = self::DEFAULT_BATCH,
        int $pauseMs = self::DEFAULT_PAUSE_MS,
        bool $keepPeriod = false
    ): array {
        $batchSize = max(100, $batchSize);
        $pauseMs = max(0, $pauseMs);

        if (! is_file($archivo) || ! is_readable($archivo)) {
            throw new RuntimeException("No se puede leer: {$archivo}");
        }

        @ini_set('memory_limit', '128M');
        gc_enable();

        $stats = [
            'leidas' => 0,
            'insertadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
            'lotes' => 0,
            'borrados' => 0,
            'desdefecha' => null,
            'hastafecha' => null,
        ];

        $handle = fopen($archivo, 'r');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir {$archivo}");
        }

        $pdo = DB::connection()->getPdo();
        $this->prepararSesion($pdo);

        $periodo = $this->detectarPeriodo($handle);
        if ($periodo === null) {
            fclose($handle);
            throw new RuntimeException('No hay líneas válidas en el archivo.');
        }

        [$desdePeriodo, $hastaPeriodo] = $periodo;
        $stats['desdefecha'] = $desdePeriodo;
        $stats['hastafecha'] = $hastaPeriodo;

        Log::info('padron_iibb_caba:carga:inicio', [
            'archivo' => $archivo,
            'desdefecha' => $desdePeriodo,
            'hastafecha' => $hastaPeriodo,
            'batch' => $batchSize,
            'pause_ms' => $pauseMs,
            'keep_period' => $keepPeriod,
        ]);

        if (! $keepPeriod) {
            $stats['borrados'] = $this->borrarPeriodoEnChunks($pdo, $desdePeriodo, $hastaPeriodo, $batchSize, $pauseMs);
            Log::info('padron_iibb_caba:carga:periodo_borrado', ['borrados' => $stats['borrados']]);
        }

        $sqlBase = 'INSERT INTO padron_iibb_caba
            (cuit, nombre, desdefecha, hastafecha, tasapercepcion, tasaretencion, tipocontribuyente)
            VALUES ';

        $lote = [];
        $t0 = microtime(true);

        try {
            while (($raw = fgets($handle)) !== false) {
                $cols = $this->parseLinea($raw);
                if ($cols === null) {
                    $stats['omitidas']++;
                    continue;
                }
                if ($cols['desdefecha'] !== $desdePeriodo || $cols['hastafecha'] !== $hastaPeriodo) {
                    $stats['omitidas']++;
                    continue;
                }

                $stats['leidas']++;
                $lote[] = $cols;

                if (count($lote) >= $batchSize) {
                    $this->flushLote($pdo, $sqlBase, $lote, $stats);
                    $lote = [];
                    if ($pauseMs > 0) {
                        usleep($pauseMs * 1000);
                    }
                    if ($stats['lotes'] % self::PROGRESS_EVERY_BATCHES === 0) {
                        $this->logProgreso($stats, $t0);
                    }
                }
            }

            if ($lote !== []) {
                $this->flushLote($pdo, $sqlBase, $lote, $stats);
            }
        } finally {
            fclose($handle);
            $this->restaurarSesion($pdo);
        }

        $this->logProgreso($stats, $t0);
        Log::info('padron_iibb_caba:carga:fin', $stats);

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

    /**
     * @param resource $handle
     * @return array{0:string,1:string}|null
     */
    private function detectarPeriodo($handle): ?array
    {
        $pos = ftell($handle);
        $periodo = null;
        while (($raw = fgets($handle)) !== false) {
            $cols = $this->parseLinea($raw);
            if ($cols !== null) {
                $periodo = [$cols['desdefecha'], $cols['hastafecha']];
                break;
            }
        }
        fseek($handle, $pos);

        return $periodo;
    }

    /**
     * @return array{cuit:string,nombre:string,desdefecha:string,hastafecha:string,tasapercepcion:float,tasaretencion:float,tipocontribuyente:string}|null
     */
    private function parseLinea(string $raw): ?array
    {
        $raw = rtrim($raw, "\r\n");
        if ($raw === '') {
            return null;
        }
        $columnas = str_getcsv($raw, ';');
        if (! is_array($columnas) || count($columnas) < 9) {
            return null;
        }

        $desde = DateTime::createFromFormat('dmY', trim((string) $columnas[1]));
        $hasta = DateTime::createFromFormat('dmY', trim((string) $columnas[2]));
        if (! $desde || ! $hasta) {
            return null;
        }

        $cuit = preg_replace('/\D+/', '', (string) $columnas[3]) ?? '';
        if ($cuit === '' || strlen($cuit) < 10) {
            return null;
        }

        $nombreRaw = (string) ($columnas[11] ?? '');
        $nombre = mb_convert_encoding($nombreRaw, 'UTF-8', 'ISO-8859-1,UTF-8');
        $nombre = mb_substr(trim($nombre) !== '' ? trim($nombre) : 'xx', 0, 255);

        return [
            'cuit' => $cuit,
            'nombre' => $nombre,
            'desdefecha' => $desde->format('Y-m-d'),
            'hastafecha' => $hasta->format('Y-m-d'),
            'tasapercepcion' => (float) str_replace(',', '.', trim((string) $columnas[7])),
            'tasaretencion' => (float) str_replace(',', '.', trim((string) $columnas[8])),
            'tipocontribuyente' => mb_substr(trim((string) ($columnas[4] ?? '')), 0, 10),
        ];
    }

    private function borrarPeriodoEnChunks(\PDO $pdo, string $desde, string $hasta, int $chunk, int $pauseMs): int
    {
        $total = 0;
        $sql = 'DELETE FROM padron_iibb_caba
                WHERE desdefecha = :d AND hastafecha = :h
                LIMIT ' . (int) $chunk;
        $stmt = $pdo->prepare($sql);

        while (true) {
            $stmt->execute([':d' => $desde, ':h' => $hasta]);
            $n = $stmt->rowCount();
            if ($n <= 0) {
                break;
            }
            $total += $n;
            if ($pauseMs > 0) {
                usleep($pauseMs * 1000);
            }
            if ($total % ($chunk * 10) === 0) {
                Log::info('padron_iibb_caba:carga:borrando', ['acumulado' => $total]);
            }
        }

        return $total;
    }

    /**
     * @param list<array<string,mixed>> $lote
     * @param array{leidas:int,insertadas:int,omitidas:int,errores:int,lotes:int} $stats
     */
    private function flushLote(\PDO $pdo, string $sqlBase, array $lote, array &$stats): void
    {
        if ($lote === []) {
            return;
        }

        $placeholders = [];
        $params = [];
        $i = 0;
        foreach ($lote as $row) {
            $placeholders[] = "(:c{$i}, :n{$i}, :d{$i}, :h{$i}, :tp{$i}, :tr{$i}, :t{$i})";
            $params[":c{$i}"] = $row['cuit'];
            $params[":n{$i}"] = $row['nombre'];
            $params[":d{$i}"] = $row['desdefecha'];
            $params[":h{$i}"] = $row['hastafecha'];
            $params[":tp{$i}"] = $row['tasapercepcion'];
            $params[":tr{$i}"] = $row['tasaretencion'];
            $params[":t{$i}"] = $row['tipocontribuyente'];
            $i++;
        }

        $sql = $sqlBase . implode(',', $placeholders);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pdo->commit();
            $stats['insertadas'] += count($lote);
            $stats['lotes']++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($lote as $row) {
                try {
                    $pdo->beginTransaction();
                    $one = $pdo->prepare($sqlBase . '(:c,:n,:d,:h,:tp,:tr,:t)');
                    $one->execute([
                        ':c' => $row['cuit'],
                        ':n' => $row['nombre'],
                        ':d' => $row['desdefecha'],
                        ':h' => $row['hastafecha'],
                        ':tp' => $row['tasapercepcion'],
                        ':tr' => $row['tasaretencion'],
                        ':t' => $row['tipocontribuyente'],
                    ]);
                    $pdo->commit();
                    $stats['insertadas']++;
                } catch (Throwable $e2) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $stats['errores']++;
                    Log::warning('padron_iibb_caba:carga:fila_error', [
                        'cuit' => $row['cuit'],
                        'error' => $e2->getMessage(),
                    ]);
                }
            }
            $stats['lotes']++;
        }

        if ($stats['lotes'] % 50 === 0) {
            gc_collect_cycles();
        }
    }

    /**
     * @param array{insertadas:int,lotes:int,errores:int} $stats
     */
    private function logProgreso(array $stats, float $t0): void
    {
        $elapsed = max(0.001, microtime(true) - $t0);
        Log::info('padron_iibb_caba:carga:progreso', [
            'insertadas' => $stats['insertadas'],
            'lotes' => $stats['lotes'],
            'errores' => $stats['errores'],
            'rate_filas_s' => (int) round($stats['insertadas'] / $elapsed),
            'mem_mb' => round(memory_get_usage(true) / 1048576, 1),
        ]);
    }
}
