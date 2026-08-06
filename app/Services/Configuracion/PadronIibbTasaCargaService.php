<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use App\Support\Configuracion\PadronIibb\PadronIibbArchivoSupport;
use App\Support\Configuracion\PadronIibb\PadronIibbLinea;
use App\Support\Configuracion\PadronIibb\PadronIibbParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Motor de carga masiva de padrones IIBB provinciales en padron_iibb + padron_iibb_tasa.
 *
 * El layout de cada provincia lo resuelve un PadronIibbParser; acá vive todo lo
 * que comparten: lectura en streaming, inserción por lotes con PDO, reemplazo
 * del período anterior y reporte de avance.
 */
class PadronIibbTasaCargaService
{
    public const DEFAULT_BATCH = 3000;

    public const DEFAULT_PAUSE_MS = 20;

    /** Cada cuántos lotes se informa avance (log y callback). */
    private const AVISAR_CADA_LOTES = 10;

    /** Tope de CUIT por sentencia IN, para no pasarse de placeholders. */
    private const CHUNK_LOOKUP = 1000;

    /**
     * @param  callable(array<string,mixed>):void|null  $onProgreso
     * @return array<string,mixed>
     */
    public function cargar(
        string $entrada,
        int $provinciaId,
        PadronIibbParser $parser,
        int $batchSize = self::DEFAULT_BATCH,
        int $pauseMs = self::DEFAULT_PAUSE_MS,
        bool $keepPeriod = false,
        ?callable $onProgreso = null,
    ): array {
        $batchSize = max(100, $batchSize);
        $pauseMs = max(0, $pauseMs);
        $provinciaId = max(1, $provinciaId);

        $archivo = PadronIibbArchivoSupport::resolver($entrada, ['csv', 'txt']);

        @ini_set('memory_limit', '256M');
        gc_enable();

        $stats = [
            'etiqueta' => $parser->etiqueta(),
            'jurisdiccion' => $parser->jurisdiccion(),
            'provincia_id' => $provinciaId,
            'archivo' => $archivo,
            'leidas' => 0,
            'omitidas' => 0,
            'insertadas_cuit' => 0,
            'nombres_actualizados' => 0,
            'insertadas_tasa' => 0,
            'actualizadas_tasa' => 0,
            'borrados' => 0,
            'errores' => 0,
            'lotes' => 0,
            'desdefecha' => null,
            'hastafecha' => null,
            'segundos' => 0.0,
        ];

        $pdo = DB::connection()->getPdo();
        $inicio = microtime(true);

        // Closures normales, no arrow functions: necesitan $stats por referencia
        // para que los contadores de cada lote lleguen al resultado final.
        $insertar = function (array $lote) use ($pdo, $provinciaId, &$stats): void {
            $this->insertarLote($pdo, $provinciaId, $lote, $stats);
        };

        $periodo = $parser->periodoUnico() ? $this->detectarPeriodo($archivo, $parser) : null;
        if ($parser->periodoUnico()) {
            if ($periodo === null) {
                throw new RuntimeException(
                    'El archivo no tiene ninguna línea válida para ' . $parser->etiqueta() . '. '
                    . 'Formato esperado: ' . $parser->formatoEsperado()
                );
            }
            [$stats['desdefecha'], $stats['hastafecha']] = $periodo;
        }

        Log::info('padron_iibb:carga:inicio', [
            'etiqueta' => $parser->etiqueta(),
            'archivo' => $archivo,
            'provincia_id' => $provinciaId,
            'desdefecha' => $stats['desdefecha'],
            'hastafecha' => $stats['hastafecha'],
            'batch' => $batchSize,
        ]);

        $this->prepararSesion($pdo);

        try {
            if (! $keepPeriod) {
                $stats['borrados'] = $this->borrarCargaAnterior($pdo, $provinciaId, $periodo, $batchSize, $pauseMs);
            }

            // Los padrones con líneas P y R separadas se recorren dos veces para que
            // el resultado no dependa del orden en que vengan dentro del archivo.
            if ($parser->separaPercepcionRetencion()) {
                if ($periodo === null) {
                    throw new RuntimeException(
                        'Un padrón con líneas de percepción y retención separadas necesita un período único.'
                    );
                }

                $this->recorrer(
                    $archivo,
                    $parser,
                    $periodo,
                    $batchSize,
                    $pauseMs,
                    $stats,
                    $onProgreso,
                    PadronIibbLinea::LADO_PERCEPCION,
                    $insertar,
                );
                $this->recorrer(
                    $archivo,
                    $parser,
                    $periodo,
                    $batchSize,
                    $pauseMs,
                    $stats,
                    $onProgreso,
                    PadronIibbLinea::LADO_RETENCION,
                    function (array $lote) use ($pdo, $provinciaId, &$stats, $periodo): void {
                        $this->aplicarRetenciones($pdo, $provinciaId, $lote, $stats, $periodo);
                    },
                );
            } else {
                $this->recorrer(
                    $archivo,
                    $parser,
                    $periodo,
                    $batchSize,
                    $pauseMs,
                    $stats,
                    $onProgreso,
                    null,
                    $insertar,
                );
            }
        } finally {
            $this->restaurarSesion($pdo);
            PadronIibbArchivoSupport::limpiarTemporal($archivo);
        }

        $stats['segundos'] = round(microtime(true) - $inicio, 1);
        Log::info('padron_iibb:carga:fin', $stats);

        if ($onProgreso !== null) {
            $onProgreso($stats);
        }

        return $stats;
    }

    /**
     * Recorre el archivo aplicando $procesar a cada lote.
     *
     * @param  array{0:string,1:string}|null  $periodo
     * @param  array<string,mixed>  $stats
     * @param  callable(array<string,mixed>):void|null  $onProgreso
     * @param  callable(list<PadronIibbLinea>):void  $procesar
     */
    private function recorrer(
        string $archivo,
        PadronIibbParser $parser,
        ?array $periodo,
        int $batchSize,
        int $pauseMs,
        array &$stats,
        ?callable $onProgreso,
        ?string $ladoFiltrado,
        callable $procesar,
    ): void {
        $handle = fopen($archivo, 'r');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir {$archivo}");
        }

        $lote = [];

        try {
            while (($raw = fgets($handle)) !== false) {
                $linea = $parser->parseLinea($raw);
                if ($linea === null) {
                    // En la segunda pasada las líneas del otro lado ya se contaron.
                    if ($ladoFiltrado === null || $ladoFiltrado === PadronIibbLinea::LADO_PERCEPCION) {
                        $stats['omitidas']++;
                    }

                    continue;
                }

                if ($ladoFiltrado !== null && $linea->lado !== $ladoFiltrado) {
                    continue;
                }

                if ($periodo !== null && ($linea->desdefecha !== $periodo[0] || $linea->hastafecha !== $periodo[1])) {
                    $stats['omitidas']++;

                    continue;
                }

                $stats['leidas']++;
                $lote[] = $linea;

                if (count($lote) >= $batchSize) {
                    $procesar($lote);
                    $lote = [];
                    $stats['lotes']++;
                    $this->pausar($pauseMs);
                    $this->avisar($stats, $onProgreso);
                }
            }

            if ($lote !== []) {
                $procesar($lote);
                $stats['lotes']++;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string,mixed>  $stats
     * @param  callable(array<string,mixed>):void|null  $onProgreso
     */
    private function avisar(array $stats, ?callable $onProgreso): void
    {
        if ($stats['lotes'] % self::AVISAR_CADA_LOTES !== 0) {
            return;
        }

        Log::info('padron_iibb:carga:progreso', [
            'etiqueta' => $stats['etiqueta'],
            'leidas' => $stats['leidas'],
            'insertadas_tasa' => $stats['insertadas_tasa'],
            'actualizadas_tasa' => $stats['actualizadas_tasa'],
            'lotes' => $stats['lotes'],
            'mem_mb' => round(memory_get_usage(true) / 1048576, 1),
        ]);

        if ($onProgreso !== null) {
            $onProgreso($stats);
        }

        gc_collect_cycles();
    }

    private function pausar(int $pauseMs): void
    {
        if ($pauseMs > 0) {
            usleep($pauseMs * 1000);
        }
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function detectarPeriodo(string $archivo, PadronIibbParser $parser): ?array
    {
        $handle = fopen($archivo, 'r');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir {$archivo}");
        }

        try {
            while (($raw = fgets($handle)) !== false) {
                $linea = $parser->parseLinea($raw);
                if ($linea !== null) {
                    return [$linea->desdefecha, $linea->hastafecha];
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    /**
     * Reemplaza la carga anterior: el período detectado, o el padrón completo de
     * la provincia cuando las vigencias varían fila por fila.
     *
     * @param  array{0:string,1:string}|null  $periodo
     */
    private function borrarCargaAnterior(
        PDO $pdo,
        int $provinciaId,
        ?array $periodo,
        int $chunk,
        int $pauseMs,
    ): int {
        if ($periodo !== null) {
            $sql = 'DELETE FROM padron_iibb_tasa
                    WHERE provincia_id = :p AND desdefecha = :d AND hastafecha = :h
                    LIMIT ' . (int) $chunk;
            $params = [':p' => $provinciaId, ':d' => $periodo[0], ':h' => $periodo[1]];
        } else {
            $sql = 'DELETE FROM padron_iibb_tasa WHERE provincia_id = :p LIMIT ' . (int) $chunk;
            $params = [':p' => $provinciaId];
        }

        $stmt = $pdo->prepare($sql);
        $total = 0;

        while (true) {
            $stmt->execute($params);
            $borradas = $stmt->rowCount();
            if ($borradas <= 0) {
                break;
            }
            $total += $borradas;
            $this->pausar($pauseMs);
        }

        Log::info('padron_iibb:carga:reemplazo', [
            'provincia_id' => $provinciaId,
            'periodo' => $periodo,
            'borrados' => $total,
        ]);

        return $total;
    }

    /**
     * @param  list<PadronIibbLinea>  $lote
     * @param  array<string,mixed>  $stats
     */
    private function insertarLote(PDO $pdo, int $provinciaId, array $lote, array &$stats): void
    {
        if ($lote === []) {
            return;
        }

        try {
            $mapa = $this->resolverCuits($pdo, $lote, $stats);
            $this->insertarTasas($pdo, $provinciaId, $lote, $mapa, $stats);
        } catch (Throwable $e) {
            // Un lote entero no debería caerse por una fila con datos raros:
            // se reintenta fila por fila para aislar y seguir con el resto.
            Log::warning('padron_iibb:carga:lote_error', [
                'filas' => count($lote),
                'error' => $e->getMessage(),
            ]);

            foreach ($lote as $linea) {
                try {
                    $mapa = $this->resolverCuits($pdo, [$linea], $stats);
                    $this->insertarTasas($pdo, $provinciaId, [$linea], $mapa, $stats);
                } catch (Throwable $eFila) {
                    $stats['errores']++;
                    Log::warning('padron_iibb:carga:fila_error', [
                        'cuit' => $linea->cuit,
                        'error' => $eFila->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Segunda pasada de los padrones P/R: completa la retención sobre la fila de
     * percepción ya insertada, o inserta la fila cuando el CUIT solo tiene retención.
     *
     * @param  list<PadronIibbLinea>  $lote
     * @param  array<string,mixed>  $stats
     * @param  array{0:string,1:string}  $periodo
     */
    private function aplicarRetenciones(
        PDO $pdo,
        int $provinciaId,
        array $lote,
        array &$stats,
        array $periodo,
    ): void {
        if ($lote === []) {
            return;
        }

        try {
            $mapa = $this->resolverCuits($pdo, $lote, $stats);

            $porPadronId = [];
            foreach ($lote as $linea) {
                $padronId = $mapa[$linea->cuit] ?? null;
                if ($padronId === null) {
                    $stats['errores']++;

                    continue;
                }
                $porPadronId[$padronId] = $linea;
            }

            if ($porPadronId === []) {
                return;
            }

            $existentes = $this->tasasExistentes($pdo, $provinciaId, array_keys($porPadronId), $periodo);

            $aActualizar = array_intersect_key($porPadronId, array_flip($existentes));
            $aInsertar = array_diff_key($porPadronId, array_flip($existentes));

            if ($aActualizar !== []) {
                $stats['actualizadas_tasa'] += $this->actualizarRetenciones(
                    $pdo,
                    $provinciaId,
                    $aActualizar,
                    $periodo
                );
            }

            if ($aInsertar !== []) {
                $this->insertarTasas($pdo, $provinciaId, array_values($aInsertar), $mapa, $stats);
            }
        } catch (Throwable $e) {
            $stats['errores'] += count($lote);
            Log::warning('padron_iibb:carga:retencion_lote_error', [
                'filas' => count($lote),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<int>  $padronIds
     * @param  array{0:string,1:string}  $periodo
     * @return list<int> ids de padron_iibb que ya tienen fila de tasa en el período
     */
    private function tasasExistentes(PDO $pdo, int $provinciaId, array $padronIds, array $periodo): array
    {
        $encontrados = [];

        foreach (array_chunk($padronIds, self::CHUNK_LOOKUP) as $chunk) {
            $placeholders = [];
            $params = [':pr' => $provinciaId, ':d' => $periodo[0], ':h' => $periodo[1]];
            foreach ($chunk as $i => $id) {
                $placeholders[] = ":i{$i}";
                $params[":i{$i}"] = $id;
            }

            $stmt = $pdo->prepare(
                'SELECT DISTINCT padron_iibb_id FROM padron_iibb_tasa
                 WHERE provincia_id = :pr AND desdefecha = :d AND hastafecha = :h
                   AND padron_iibb_id IN (' . implode(',', $placeholders) . ')'
            );
            $stmt->execute($params);

            while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $encontrados[] = (int) $fila['padron_iibb_id'];
            }
        }

        return $encontrados;
    }

    /**
     * @param  array<int,PadronIibbLinea>  $porPadronId
     * @param  array{0:string,1:string}  $periodo
     */
    private function actualizarRetenciones(
        PDO $pdo,
        int $provinciaId,
        array $porPadronId,
        array $periodo,
    ): int {
        $actualizadas = 0;

        foreach (array_chunk($porPadronId, self::CHUNK_LOOKUP, true) as $chunk) {
            $casos = [];
            $placeholders = [];
            $params = [':pr' => $provinciaId, ':d' => $periodo[0], ':h' => $periodo[1]];
            $i = 0;

            foreach ($chunk as $padronId => $linea) {
                $casos[] = "WHEN :k{$i} THEN :v{$i}";
                $placeholders[] = ":in{$i}";
                $params[":k{$i}"] = $padronId;
                $params[":in{$i}"] = $padronId;
                $params[":v{$i}"] = $linea->tasaretencion;
                $i++;
            }

            $sql = 'UPDATE padron_iibb_tasa
                    SET tasaretencion = CASE padron_iibb_id ' . implode(' ', $casos) . ' END,
                        updated_at = NOW()
                    WHERE provincia_id = :pr AND desdefecha = :d AND hastafecha = :h
                      AND padron_iibb_id IN (' . implode(',', $placeholders) . ')';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $actualizadas += $stmt->rowCount();
        }

        return $actualizadas;
    }

    /**
     * Devuelve cuit => padron_iibb.id, dando de alta los CUIT que falten.
     *
     * @param  list<PadronIibbLinea>  $lote
     * @param  array<string,mixed>  $stats
     * @return array<string,int>
     */
    private function resolverCuits(PDO $pdo, array $lote, array &$stats): array
    {
        $nombrePorCuit = [];
        foreach ($lote as $linea) {
            // Si el mismo CUIT aparece varias veces, gana la primera razón social no vacía.
            $nombrePorCuit[$linea->cuit] ??= $linea->nombre;
            if ($nombrePorCuit[$linea->cuit] === null && $linea->nombre !== null) {
                $nombrePorCuit[$linea->cuit] = $linea->nombre;
            }
        }

        $mapa = $this->seleccionarCuits($pdo, array_keys($nombrePorCuit));

        $faltantes = array_diff_key($nombrePorCuit, $mapa);
        if ($faltantes !== []) {
            $stats['insertadas_cuit'] += $this->insertarCuits($pdo, $faltantes);
            $mapa += $this->seleccionarCuits($pdo, array_keys($faltantes));
        }

        $conNombre = array_filter($nombrePorCuit, static fn (?string $n): bool => $n !== null);
        if ($conNombre !== []) {
            $stats['nombres_actualizados'] += $this->completarNombres($pdo, $conNombre);
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $cuits
     * @return array<string,int>
     */
    private function seleccionarCuits(PDO $pdo, array $cuits): array
    {
        $mapa = [];

        foreach (array_chunk($cuits, self::CHUNK_LOOKUP) as $chunk) {
            $placeholders = [];
            $params = [];
            foreach ($chunk as $i => $cuit) {
                $placeholders[] = ":c{$i}";
                $params[":c{$i}"] = $cuit;
            }

            $stmt = $pdo->prepare(
                'SELECT id, cuit FROM padron_iibb WHERE cuit IN (' . implode(',', $placeholders) . ')'
            );
            $stmt->execute($params);

            while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $mapa[(string) $fila['cuit']] = (int) $fila['id'];
            }
        }

        return $mapa;
    }

    /**
     * @param  array<string,string|null>  $nombrePorCuit
     */
    private function insertarCuits(PDO $pdo, array $nombrePorCuit): int
    {
        $ahora = date('Y-m-d H:i:s');
        $insertados = 0;

        foreach (array_chunk($nombrePorCuit, self::CHUNK_LOOKUP, true) as $chunk) {
            $placeholders = [];
            $params = [];
            $i = 0;
            foreach ($chunk as $cuit => $nombre) {
                // PDO con prepares reales no admite el mismo placeholder repetido.
                $placeholders[] = "(:c{$i}, :n{$i}, :ca{$i}, :ua{$i})";
                $params[":c{$i}"] = $cuit;
                $params[":n{$i}"] = $nombre;
                $params[":ca{$i}"] = $ahora;
                $params[":ua{$i}"] = $ahora;
                $i++;
            }

            $sql = 'INSERT INTO padron_iibb (cuit, nombre, created_at, updated_at) VALUES '
                . implode(',', $placeholders);

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $insertados += count($chunk);
            } catch (Throwable $e) {
                Log::warning('padron_iibb:carga:alta_cuit_error', ['error' => $e->getMessage()]);
                $insertados += $this->insertarCuitsUnoAUno($pdo, $chunk, $ahora);
            }
        }

        return $insertados;
    }

    /**
     * @param  array<string,string|null>  $nombrePorCuit
     */
    private function insertarCuitsUnoAUno(PDO $pdo, array $nombrePorCuit, string $ahora): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO padron_iibb (cuit, nombre, created_at, updated_at) VALUES (:c, :n, :ca, :ua)'
        );
        $insertados = 0;

        foreach ($nombrePorCuit as $cuit => $nombre) {
            try {
                $stmt->execute([':c' => $cuit, ':n' => $nombre, ':ca' => $ahora, ':ua' => $ahora]);
                $insertados++;
            } catch (Throwable $e) {
                // El CUIT ya existe: lo resuelve el SELECT posterior.
            }
        }

        return $insertados;
    }

    /**
     * Completa la razón social de los CUIT que se habían dado de alta sin nombre.
     *
     * @param  array<string,string>  $nombrePorCuit
     */
    private function completarNombres(PDO $pdo, array $nombrePorCuit): int
    {
        $actualizados = 0;

        foreach (array_chunk($nombrePorCuit, self::CHUNK_LOOKUP, true) as $chunk) {
            $casos = [];
            $placeholders = [];
            $params = [];
            $i = 0;

            foreach ($chunk as $cuit => $nombre) {
                $casos[] = "WHEN :k{$i} THEN :v{$i}";
                $placeholders[] = ":in{$i}";
                $params[":k{$i}"] = $cuit;
                $params[":in{$i}"] = $cuit;
                $params[":v{$i}"] = $nombre;
                $i++;
            }

            $sql = 'UPDATE padron_iibb
                    SET nombre = CASE cuit ' . implode(' ', $casos) . ' END, updated_at = NOW()
                    WHERE cuit IN (' . implode(',', $placeholders) . ')
                      AND (nombre IS NULL OR nombre = \'\')';

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $actualizados += $stmt->rowCount();
            } catch (Throwable $e) {
                Log::warning('padron_iibb:carga:nombre_error', ['error' => $e->getMessage()]);
            }
        }

        return $actualizados;
    }

    /**
     * @param  list<PadronIibbLinea>  $lote
     * @param  array<string,int>  $mapa
     * @param  array<string,mixed>  $stats
     */
    private function insertarTasas(PDO $pdo, int $provinciaId, array $lote, array $mapa, array &$stats): void
    {
        $ahora = date('Y-m-d H:i:s');
        $placeholders = [];
        $params = [];
        $i = 0;

        foreach ($lote as $linea) {
            $padronId = $mapa[$linea->cuit] ?? null;
            if ($padronId === null) {
                $stats['errores']++;

                continue;
            }

            $placeholders[] = "(:p{$i}, :pr{$i}, :d{$i}, :h{$i}, :tp{$i}, :tr{$i}, :co{$i}, :ti{$i}, :ri{$i}, :ex{$i}, :ca{$i}, :ua{$i})";
            $params[":p{$i}"] = $padronId;
            $params[":pr{$i}"] = $provinciaId;
            $params[":d{$i}"] = $linea->desdefecha;
            $params[":h{$i}"] = $linea->hastafecha;
            $params[":tp{$i}"] = $linea->tasapercepcion;
            $params[":tr{$i}"] = $linea->tasaretencion;
            $params[":co{$i}"] = $linea->coeficiente;
            $params[":ti{$i}"] = $linea->tipocontribuyente;
            $params[":ri{$i}"] = $linea->riesgofiscal;
            $params[":ex{$i}"] = $linea->excluido;
            $params[":ca{$i}"] = $ahora;
            $params[":ua{$i}"] = $ahora;
            $i++;
        }

        if ($placeholders === []) {
            return;
        }

        $sql = 'INSERT INTO padron_iibb_tasa
                (padron_iibb_id, provincia_id, desdefecha, hastafecha, tasapercepcion, tasaretencion,
                 coeficiente, tipocontribuyente, riesgofiscal, excluido, created_at, updated_at)
                VALUES ' . implode(',', $placeholders);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stats['insertadas_tasa'] += count($placeholders);
    }

    private function prepararSesion(PDO $pdo): void
    {
        foreach ([
            'SET SESSION innodb_lock_wait_timeout = 30',
            'SET SESSION unique_checks = 0',
            'SET SESSION foreign_key_checks = 0',
        ] as $sentencia) {
            try {
                $pdo->exec($sentencia);
            } catch (Throwable $e) {
                // Motor sin soporte para la variable: la carga sigue igual.
            }
        }
    }

    private function restaurarSesion(PDO $pdo): void
    {
        foreach ([
            'SET SESSION unique_checks = 1',
            'SET SESSION foreign_key_checks = 1',
        ] as $sentencia) {
            try {
                $pdo->exec($sentencia);
            } catch (Throwable $e) {
                // Idem prepararSesion.
            }
        }
    }
}
