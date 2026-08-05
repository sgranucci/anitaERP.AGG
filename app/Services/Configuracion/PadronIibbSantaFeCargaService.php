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
 * Carga masiva padrón IIBB Santa Fe (API PARP) en padron_iibb + padron_iibb_tasa.
 *
 * Formato CSV (;): F.PUBLIC;F.VIGEN.DESDE;F.VIGEN.HASTA;CUIT;TIPO;…;ALIC.PERCEP;ALIC.RETEN;…;RAZON SOCIAL
 * Acepta CSV o ZIP con un CSV PARP_YYYYMM.csv.
 */
class PadronIibbSantaFeCargaService
{
    public const DEFAULT_BATCH = 3000;

    public const DEFAULT_PAUSE_MS = 10;

    public const JURISDICCION = 921;

    private const PROGRESS_EVERY_BATCHES = 20;

    /**
     * @return array{
     *   leidas:int,insertadas_cuit:int,insertadas_tasa:int,omitidas:int,errores:int,lotes:int,
     *   borrados:int,desdefecha:?string,hastafecha:?string,archivo:string,provincia_id:int
     * }
     */
    public function cargar(
        string $entrada,
        int $provinciaId,
        int $batchSize = self::DEFAULT_BATCH,
        int $pauseMs = self::DEFAULT_PAUSE_MS,
        bool $keepPeriod = false
    ): array {
        $batchSize = max(100, $batchSize);
        $pauseMs = max(0, $pauseMs);
        $provinciaId = max(1, $provinciaId);

        $archivo = $this->resolverArchivoCsv($entrada);
        if (! is_file($archivo) || ! is_readable($archivo)) {
            throw new RuntimeException("No se puede leer: {$archivo}");
        }

        @ini_set('memory_limit', '256M');
        gc_enable();

        $stats = [
            'leidas' => 0,
            'insertadas_cuit' => 0,
            'insertadas_tasa' => 0,
            'omitidas' => 0,
            'errores' => 0,
            'lotes' => 0,
            'borrados' => 0,
            'desdefecha' => null,
            'hastafecha' => null,
            'archivo' => $archivo,
            'provincia_id' => $provinciaId,
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
            throw new RuntimeException('No hay líneas válidas en el padrón Santa Fe.');
        }

        [$desdePeriodo, $hastaPeriodo] = $periodo;
        $stats['desdefecha'] = $desdePeriodo;
        $stats['hastafecha'] = $hastaPeriodo;

        Log::info('padron_iibb_santafe:carga:inicio', [
            'entrada' => $entrada,
            'archivo' => $archivo,
            'provincia_id' => $provinciaId,
            'desdefecha' => $desdePeriodo,
            'hastafecha' => $hastaPeriodo,
            'batch' => $batchSize,
            'pause_ms' => $pauseMs,
            'keep_period' => $keepPeriod,
        ]);

        if (! $keepPeriod) {
            $stats['borrados'] = $this->borrarPeriodoEnChunks(
                $pdo,
                $provinciaId,
                $desdePeriodo,
                $hastaPeriodo,
                $batchSize,
                $pauseMs
            );
            Log::info('padron_iibb_santafe:carga:periodo_borrado', ['borrados' => $stats['borrados']]);
        }

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
                    $this->flushLote($pdo, $provinciaId, $lote, $stats);
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
                $this->flushLote($pdo, $provinciaId, $lote, $stats);
            }
        } finally {
            fclose($handle);
            $this->restaurarSesion($pdo);
        }

        $this->logProgreso($stats, $t0);
        Log::info('padron_iibb_santafe:carga:fin', $stats);

        return $stats;
    }

    public function resolverArchivoCsv(string $entrada): string
    {
        if (! is_file($entrada)) {
            throw new RuntimeException("No existe: {$entrada}");
        }

        $ext = strtolower(pathinfo($entrada, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return $entrada;
        }

        $destDir = dirname($entrada);
        $zip = new ZipArchive;
        if ($zip->open($entrada) !== true) {
            throw new RuntimeException("Error al abrir zip: {$entrada}");
        }

        $candidatos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) ($zip->statIndex($i)['name'] ?? '');
            $base = basename($name);
            if ($base === '' || str_ends_with($base, '/')) {
                continue;
            }
            $e = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if ($e !== 'csv' && $e !== 'txt') {
                continue;
            }
            $candidatos[] = $base;
        }

        if ($candidatos === []) {
            $zip->close();
            throw new RuntimeException('El ZIP no contiene CSV/TXT del padrón Santa Fe.');
        }

        usort($candidatos, static function (string $a, string $b): int {
            $sa = str_contains(strtoupper($a), 'PARP') ? 0 : 1;
            $sb = str_contains(strtoupper($b), 'PARP') ? 0 : 1;

            return $sa <=> $sb ?: strcmp($a, $b);
        });

        $zip->extractTo($destDir);
        $zip->close();

        $csv = $destDir . DIRECTORY_SEPARATOR . $candidatos[0];
        if (! is_file($csv)) {
            throw new RuntimeException("No se pudo extraer {$candidatos[0]} desde el ZIP.");
        }

        return $csv;
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
    public function parseLinea(string $raw): ?array
    {
        $raw = rtrim($raw, "\r\n");
        if ($raw === '') {
            return null;
        }

        $columnas = str_getcsv($raw, ';');
        if (! is_array($columnas) || count($columnas) < 9) {
            return null;
        }

        // Cabecera PARP
        $c0 = trim((string) $columnas[0]);
        if ($c0 === '' || ! ctype_digit(substr($c0, 0, 1))) {
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
        $nombre = @mb_convert_encoding($nombreRaw, 'UTF-8', 'ISO-8859-1,UTF-8') ?: $nombreRaw;
        $nombre = mb_substr(trim($nombre) !== '' ? trim($nombre) : 'xx', 0, 255);

        return [
            'cuit' => $cuit,
            'nombre' => $nombre,
            'desdefecha' => $desde->format('Y-m-d'),
            'hastafecha' => $hasta->format('Y-m-d'),
            'tasapercepcion' => (float) str_replace(',', '.', trim((string) $columnas[7])),
            'tasaretencion' => (float) str_replace(',', '.', trim((string) ($columnas[8] ?? '0'))),
            'tipocontribuyente' => mb_substr(trim((string) ($columnas[4] ?? '')), 0, 10),
        ];
    }

    private function borrarPeriodoEnChunks(
        \PDO $pdo,
        int $provinciaId,
        string $desde,
        string $hasta,
        int $chunk,
        int $pauseMs
    ): int {
        $total = 0;
        $sql = 'DELETE FROM padron_iibb_tasa
                WHERE provincia_id = :p AND desdefecha = :d AND hastafecha = :h
                LIMIT ' . (int) $chunk;
        $stmt = $pdo->prepare($sql);

        while (true) {
            $stmt->execute([':p' => $provinciaId, ':d' => $desde, ':h' => $hasta]);
            $n = $stmt->rowCount();
            if ($n <= 0) {
                break;
            }
            $total += $n;
            if ($pauseMs > 0) {
                usleep($pauseMs * 1000);
            }
            if ($total % ($chunk * 10) === 0) {
                Log::info('padron_iibb_santafe:carga:borrando', ['acumulado' => $total]);
            }
        }

        return $total;
    }

    /**
     * @param list<array<string,mixed>> $lote
     * @param array<string,mixed> $stats
     */
    private function flushLote(\PDO $pdo, int $provinciaId, array $lote, array &$stats): void
    {
        if ($lote === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $cuits = [];
        foreach ($lote as $row) {
            $cuits[$row['cuit']] = $row['nombre'];
        }

        try {
            $mapa = $this->resolverCuits($pdo, $cuits, $now, $stats);
            $this->insertarTasas($pdo, $provinciaId, $lote, $mapa, $now, $stats);
            $stats['lotes']++;
        } catch (Throwable $e) {
            Log::warning('padron_iibb_santafe:carga:lote_error', [
                'size' => count($lote),
                'error' => $e->getMessage(),
            ]);
            foreach ($lote as $row) {
                try {
                    $mapa = $this->resolverCuits($pdo, [$row['cuit'] => $row['nombre']], $now, $stats);
                    $this->insertarTasas($pdo, $provinciaId, [$row], $mapa, $now, $stats);
                } catch (Throwable $e2) {
                    $stats['errores']++;
                    Log::warning('padron_iibb_santafe:carga:fila_error', [
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
     * @param array<string,string> $cuits nombre por cuit
     * @param array<string,mixed> $stats
     * @return array<string,int> cuit => id
     */
    private function resolverCuits(\PDO $pdo, array $cuits, string $now, array &$stats): array
    {
        if ($cuits === []) {
            return [];
        }

        $keys = array_keys($cuits);
        $mapa = $this->seleccionarCuits($pdo, $keys);

        $faltan = [];
        foreach ($cuits as $cuit => $nombre) {
            if (! isset($mapa[$cuit])) {
                $faltan[$cuit] = $nombre;
            }
        }

        if ($faltan !== []) {
            $placeholders = [];
            $params = [];
            $i = 0;
            foreach ($faltan as $cuit => $nombre) {
                $placeholders[] = "(:c{$i}, :n{$i}, :ca{$i}, :ua{$i})";
                $params[":c{$i}"] = $cuit;
                $params[":n{$i}"] = $nombre;
                $params[":ca{$i}"] = $now;
                $params[":ua{$i}"] = $now;
                $i++;
            }

            $sql = 'INSERT INTO padron_iibb (cuit, nombre, created_at, updated_at) VALUES '
                . implode(',', $placeholders);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $pdo->commit();
                $stats['insertadas_cuit'] += count($faltan);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // carrera / duplicados: insertar uno a uno
                foreach ($faltan as $cuit => $nombre) {
                    try {
                        $one = $pdo->prepare(
                            'INSERT INTO padron_iibb (cuit, nombre, created_at, updated_at) VALUES (:c,:n,:ca,:ua)'
                        );
                        $one->execute([':c' => $cuit, ':n' => $nombre, ':ca' => $now, ':ua' => $now]);
                        $stats['insertadas_cuit']++;
                    } catch (Throwable $e2) {
                        // ya existe
                    }
                }
            }

            $mapa = $mapa + $this->seleccionarCuits($pdo, array_keys($faltan));
        }

        return $mapa;
    }

    /**
     * @param list<string> $cuits
     * @return array<string,int>
     */
    private function seleccionarCuits(\PDO $pdo, array $cuits): array
    {
        if ($cuits === []) {
            return [];
        }

        $mapa = [];
        foreach (array_chunk($cuits, 1000) as $chunk) {
            $in = [];
            $params = [];
            foreach ($chunk as $i => $cuit) {
                $in[] = ":c{$i}";
                $params[":c{$i}"] = $cuit;
            }
            $sql = 'SELECT id, cuit FROM padron_iibb WHERE cuit IN (' . implode(',', $in) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $mapa[(string) $row['cuit']] = (int) $row['id'];
            }
        }

        return $mapa;
    }

    /**
     * @param list<array<string,mixed>> $lote
     * @param array<string,int> $mapa
     * @param array<string,mixed> $stats
     */
    private function insertarTasas(
        \PDO $pdo,
        int $provinciaId,
        array $lote,
        array $mapa,
        string $now,
        array &$stats
    ): void {
        $placeholders = [];
        $params = [];
        $i = 0;
        foreach ($lote as $row) {
            $id = $mapa[$row['cuit']] ?? null;
            if ($id === null) {
                $stats['errores']++;
                continue;
            }
            $placeholders[] = "(:p{$i}, :pr{$i}, :d{$i}, :h{$i}, :tp{$i}, :tr{$i}, :t{$i}, :ca{$i}, :ua{$i})";
            $params[":p{$i}"] = $id;
            $params[":pr{$i}"] = $provinciaId;
            $params[":d{$i}"] = $row['desdefecha'];
            $params[":h{$i}"] = $row['hastafecha'];
            $params[":tp{$i}"] = $row['tasapercepcion'];
            $params[":tr{$i}"] = $row['tasaretencion'];
            $params[":t{$i}"] = $row['tipocontribuyente'];
            $params[":ca{$i}"] = $now;
            $params[":ua{$i}"] = $now;
            $i++;
        }

        if ($placeholders === []) {
            return;
        }

        $sql = 'INSERT INTO padron_iibb_tasa
            (padron_iibb_id, provincia_id, desdefecha, hastafecha, tasapercepcion, tasaretencion, tipocontribuyente, created_at, updated_at)
            VALUES ' . implode(',', $placeholders);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pdo->commit();
            $stats['insertadas_tasa'] += count($placeholders);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $stats
     */
    private function logProgreso(array $stats, float $t0): void
    {
        $elapsed = max(0.001, microtime(true) - $t0);
        Log::info('padron_iibb_santafe:carga:progreso', [
            'leidas' => $stats['leidas'],
            'insertadas_tasa' => $stats['insertadas_tasa'],
            'insertadas_cuit' => $stats['insertadas_cuit'],
            'lotes' => $stats['lotes'],
            'errores' => $stats['errores'],
            'rate_filas_s' => (int) round(($stats['insertadas_tasa'] ?? 0) / $elapsed),
            'mem_mb' => round(memory_get_usage(true) / 1048576, 1),
        ]);
    }
}
