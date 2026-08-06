<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use App\Support\Configuracion\PadronIibb\PadronIibbArchivoSupport;
use App\Support\Configuracion\PadronIibb\PadronIibbCampoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Carga del padrón de coeficientes de Tucumán en padron_coeficiente_tucuman.
 *
 * Archivo de ancho fijo. La tabla guarda una fila por CUIT (así la consulta por
 * CUIT devuelve siempre el coeficiente vigente), por eso cada carga reemplaza el
 * padrón completo en lugar de acumular períodos.
 */
class PadronIibbTucumanCoeficienteCargaService
{
    public const DEFAULT_BATCH = 2000;

    public const DEFAULT_PAUSE_MS = 20;

    private const POS_CUIT = [0, 11];

    private const POS_EXCLUIDO = [13, 1];

    private const POS_COEFICIENTE = [16, 6];

    private const POS_PERIODO = [24, 6];

    private const POS_NOMBRE = [32, 60];

    private const POS_COEFICIENTE_FINAL = [184, 6];

    private const LARGO_MINIMO = 190;

    /**
     * @param  callable(array<string,mixed>):void|null  $onProgreso
     * @return array<string,mixed>
     */
    public function cargar(
        string $entrada,
        int $batchSize = self::DEFAULT_BATCH,
        int $pauseMs = self::DEFAULT_PAUSE_MS,
        ?callable $onProgreso = null,
    ): array {
        $batchSize = max(100, $batchSize);
        $pauseMs = max(0, $pauseMs);

        $archivo = PadronIibbArchivoSupport::resolver($entrada, ['csv', 'txt']);

        @ini_set('memory_limit', '256M');
        gc_enable();

        $stats = [
            'etiqueta' => 'IIBB Tucumán (coeficientes)',
            'jurisdiccion' => 924,
            'archivo' => $archivo,
            'leidas' => 0,
            'omitidas' => 0,
            'insertadas' => 0,
            'borrados' => 0,
            'errores' => 0,
            'lotes' => 0,
            'desdefecha' => null,
            'hastafecha' => null,
            'segundos' => 0.0,
        ];

        $pdo = DB::connection()->getPdo();
        $inicio = microtime(true);

        $handle = fopen($archivo, 'r');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir {$archivo}");
        }

        Log::info('padron_coeficiente_tucuman:carga:inicio', ['archivo' => $archivo]);

        $stats['borrados'] = $this->vaciarPadron($pdo, $batchSize, $pauseMs);

        $lote = [];

        try {
            while (($raw = fgets($handle)) !== false) {
                $fila = $this->parseLinea($raw);
                if ($fila === null) {
                    $stats['omitidas']++;

                    continue;
                }

                $stats['desdefecha'] ??= $fila['desdefecha'];
                $stats['hastafecha'] ??= $fila['hastafecha'];
                $stats['leidas']++;
                $lote[] = $fila;

                if (count($lote) >= $batchSize) {
                    $this->insertarLote($pdo, $lote, $stats);
                    $lote = [];
                    $stats['lotes']++;
                    if ($pauseMs > 0) {
                        usleep($pauseMs * 1000);
                    }
                    if ($onProgreso !== null && $stats['lotes'] % 10 === 0) {
                        $onProgreso($stats);
                    }
                }
            }

            if ($lote !== []) {
                $this->insertarLote($pdo, $lote, $stats);
                $stats['lotes']++;
            }
        } finally {
            fclose($handle);
            PadronIibbArchivoSupport::limpiarTemporal($archivo);
        }

        $stats['segundos'] = round(microtime(true) - $inicio, 1);
        Log::info('padron_coeficiente_tucuman:carga:fin', $stats);

        if ($onProgreso !== null) {
            $onProgreso($stats);
        }

        return $stats;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function parseLinea(string $raw): ?array
    {
        $linea = rtrim($raw, "\r\n");
        if (strlen($linea) < self::LARGO_MINIMO) {
            return null;
        }

        $cuit = PadronIibbCampoSupport::cuit($this->trozo($linea, self::POS_CUIT));
        if ($cuit === null) {
            return null;
        }

        $periodo = PadronIibbCampoSupport::periodoMensual($this->trozo($linea, self::POS_PERIODO));
        if ($periodo === null) {
            return null;
        }

        return [
            'cuit' => $cuit,
            // La columna nombre es NOT NULL en la tabla.
            'nombre' => PadronIibbCampoSupport::nombre($this->trozo($linea, self::POS_NOMBRE)) ?? '',
            'desdefecha' => $periodo[0],
            'hastafecha' => $periodo[1],
            'coeficiente' => PadronIibbCampoSupport::tasa($this->trozo($linea, self::POS_COEFICIENTE)),
            'coeficientefinal' => PadronIibbCampoSupport::tasa($this->trozo($linea, self::POS_COEFICIENTE_FINAL)),
            'tipocontribuyente' => 'C',
            'excluido' => PadronIibbCampoSupport::texto($this->trozo($linea, self::POS_EXCLUIDO)),
        ];
    }

    /** @param array{0:int,1:int} $posicion */
    private function trozo(string $linea, array $posicion): string
    {
        return substr($linea, $posicion[0], $posicion[1]);
    }

    private function vaciarPadron(PDO $pdo, int $chunk, int $pauseMs): int
    {
        $stmt = $pdo->prepare('DELETE FROM padron_coeficiente_tucuman LIMIT ' . (int) $chunk);
        $total = 0;

        while (true) {
            $stmt->execute();
            $borradas = $stmt->rowCount();
            if ($borradas <= 0) {
                break;
            }
            $total += $borradas;
            if ($pauseMs > 0) {
                usleep($pauseMs * 1000);
            }
        }

        return $total;
    }

    /**
     * @param  list<array<string,mixed>>  $lote
     * @param  array<string,mixed>  $stats
     */
    private function insertarLote(PDO $pdo, array $lote, array &$stats): void
    {
        $ahora = date('Y-m-d H:i:s');
        $placeholders = [];
        $params = [];

        foreach ($lote as $i => $fila) {
            // PDO con prepares reales no admite el mismo placeholder repetido.
            $placeholders[] = "(:c{$i}, :n{$i}, :d{$i}, :h{$i}, :co{$i}, :cf{$i}, :ti{$i}, :ex{$i}, :ca{$i}, :ua{$i})";
            $params[":c{$i}"] = $fila['cuit'];
            $params[":n{$i}"] = $fila['nombre'];
            $params[":d{$i}"] = $fila['desdefecha'];
            $params[":h{$i}"] = $fila['hastafecha'];
            $params[":co{$i}"] = $fila['coeficiente'];
            $params[":cf{$i}"] = $fila['coeficientefinal'];
            $params[":ti{$i}"] = $fila['tipocontribuyente'];
            $params[":ex{$i}"] = $fila['excluido'];
            $params[":ca{$i}"] = $ahora;
            $params[":ua{$i}"] = $ahora;
        }

        $sql = 'INSERT INTO padron_coeficiente_tucuman
                (cuit, nombre, desdefecha, hastafecha, coeficiente, coeficientefinal,
                 tipocontribuyente, excluido, created_at, updated_at)
                VALUES ' . implode(',', $placeholders);

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats['insertadas'] += count($lote);
        } catch (Throwable $e) {
            $stats['errores'] += count($lote);
            Log::warning('padron_coeficiente_tucuman:carga:lote_error', [
                'filas' => count($lote),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
