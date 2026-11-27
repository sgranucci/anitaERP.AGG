<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use App\Support\Configuracion\PadronIibb\PadronIibbArchivoSupport;
use App\Support\Configuracion\Sircip\SircipCampo7;
use App\Support\Configuracion\Sircip\SircipPadronLineaParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga el padrón SIRCIP mensual en padron_sircip (un archivo para todas las
 * jurisdicciones). Acepta el ZIP del portal o el TXT de adentro.
 *
 * analizar() solo lee el archivo: no escribe nada. cargar() persiste y debe
 * llamarse únicamente con autorización explícita (comando padron-sircip:import
 * con --ejecutar, o la bandeja).
 *
 * Reemplazo por período: el upsert deja todas las filas del archivo con
 * updated_at = inicio de la carga; después se borran las del mismo período que
 * quedaron con updated_at anterior (CUIT que ya no vienen). Todo en una sola
 * transacción: la tabla nunca queda sin padrón mientras se carga.
 */
final class PadronSircipCargaService
{
    public const LOTE = 1000;

    /**
     * Resumen de lo que haría la carga, sin tocar la base.
     *
     * @return array<string, mixed>
     */
    public function analizar(string $entrada): array
    {
        $archivo = $this->resolver($entrada);
        try {
            return $this->analizarArchivo($archivo) + ['archivo' => $entrada];
        } finally {
            $this->liberar($entrada, $archivo);
        }
    }

    /**
     * Persiste el padrón de UN período y reemplaza ese período.
     *
     * @param  callable(array<string,mixed>):void|null  $onProgreso  recibe stats parciales
     * @return array<string, mixed>
     */
    public function cargar(string $entrada, ?callable $onProgreso = null): array
    {
        $inicio = microtime(true);
        $archivo = $this->resolver($entrada);

        try {
            $resumen = $this->analizarArchivo($archivo);
            if ($resumen['filas_validas'] === 0) {
                throw new RuntimeException('El archivo no tiene filas válidas de padrón SIRCIP.');
            }
            if (count($resumen['periodos']) !== 1) {
                throw new RuntimeException(
                    'El archivo trae más de un período: '.implode(', ', $resumen['periodos']).'. Cargar uno por vez.'
                );
            }
            $periodo = (string) $resumen['periodos'][0];

            $ahora = now()->toDateTimeString();
            $persistidas = 0;
            $borradas = 0;

            DB::transaction(function () use ($archivo, $periodo, $ahora, $onProgreso, &$persistidas, &$borradas): void {
                $fh = fopen($archivo, 'rb');
                $lote = [];
                $lineaNro = 0;
                $volcar = function () use (&$lote, &$persistidas, $ahora, $onProgreso): void {
                    if ($lote === []) {
                        return;
                    }
                    $filas = array_map(
                        static fn (array $f) => $f + ['created_at' => $ahora, 'updated_at' => $ahora],
                        $lote,
                    );
                    DB::table('padron_sircip')->upsert(
                        $filas,
                        ['periodo', 'cuit'],
                        ['razon_social', 'jurisdiccion_sede', 'crc', 'alicuota_letra', 'campo7', 'updated_at'],
                    );
                    $persistidas += count($lote);
                    $lote = [];
                    if ($onProgreso !== null && $persistidas % (self::LOTE * 20) === 0) {
                        $onProgreso(['leidas' => $persistidas, 'insertadas' => $persistidas]);
                    }
                };

                while (($linea = fgets($fh)) !== false) {
                    $lineaNro++;
                    if ($lineaNro === 1 && str_starts_with(strtolower(trim($linea)), 'periodo,')) {
                        continue;
                    }
                    $fila = SircipPadronLineaParser::parsear($linea);
                    if ($fila === null) {
                        continue;
                    }
                    $lote[] = $fila;
                    if (count($lote) >= self::LOTE) {
                        $volcar();
                    }
                }
                $volcar();
                fclose($fh);

                $borradas = $this->borrarSobrantesDelPeriodo($periodo, $ahora);
            });

            $desde = Carbon::createFromFormat('Ym', $periodo)->startOfMonth();

            return $resumen + [
                'archivo' => $entrada,
                'periodo' => $periodo,
                'filas_persistidas' => $persistidas,
                'filas_borradas' => $borradas,
                // Claves que entiende PadronIibbCargaRegistroSupport
                'leidas' => $resumen['filas_leidas'],
                'insertadas' => $persistidas,
                'borrados' => $borradas,
                'omitidas' => $resumen['filas_invalidas'],
                'errores' => 0,
                'desdefecha' => $desde->toDateString(),
                'hastafecha' => $desde->copy()->endOfMonth()->toDateString(),
                'segundos' => round(microtime(true) - $inicio, 1),
            ];
        } finally {
            $this->liberar($entrada, $archivo);
        }
    }

    /**
     * Filas del período que no vinieron en esta carga (updated_at anterior).
     * En tandas para no dejar un DELETE gigante tomando locks.
     */
    private function borrarSobrantesDelPeriodo(string $periodo, string $ahora): int
    {
        $total = 0;
        do {
            $n = DB::table('padron_sircip')
                ->where('periodo', $periodo)
                ->where('updated_at', '<', $ahora)
                ->limit(50000)
                ->delete();
            $total += $n;
        } while ($n > 0);

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function analizarArchivo(string $archivo): array
    {
        $leidas = 0;
        $validas = 0;
        $invalidas = 0;
        $periodos = [];
        $cuits = [];
        $duplicados = 0;
        $letras = [];
        $adheridas = [];
        $ejemploInvalidas = [];
        $lineaNro = 0;

        $fh = fopen($archivo, 'rb');
        while (($linea = fgets($fh)) !== false) {
            $lineaNro++;
            if ($lineaNro === 1 && str_starts_with(strtolower(trim($linea)), 'periodo,')) {
                continue;
            }
            if (trim($linea) === '') {
                continue;
            }
            $leidas++;

            $fila = SircipPadronLineaParser::parsear($linea);
            if ($fila === null) {
                $invalidas++;
                if (count($ejemploInvalidas) < 5) {
                    $ejemploInvalidas[] = ['linea' => $lineaNro, 'contenido' => mb_substr(trim($linea), 0, 120)];
                }

                continue;
            }

            $validas++;
            $periodos[$fila['periodo']] = true;
            if (isset($cuits[$fila['cuit']])) {
                $duplicados++;
            }
            $cuits[$fila['cuit']] = true;
            $letras[$fila['alicuota_letra']] = ($letras[$fila['alicuota_letra']] ?? 0) + 1;

            foreach (SircipCampo7::jurisdiccionesAdheridas($fila['campo7']) as $j) {
                $adheridas[$j] = ($adheridas[$j] ?? 0) + 1;
            }
        }
        fclose($fh);

        ksort($letras);
        ksort($adheridas);

        return [
            'filas_leidas' => $leidas,
            'filas_validas' => $validas,
            'filas_invalidas' => $invalidas,
            'ejemplos_invalidas' => $ejemploInvalidas,
            'cuits_distintos' => count($cuits),
            'cuits_duplicados' => $duplicados,
            'periodos' => array_keys($periodos),
            'letras' => $letras,
            'jurisdicciones_adheridas' => $adheridas,
        ];
    }

    /** Devuelve el TXT/CSV a leer (extrae el ZIP del portal si hace falta). */
    private function resolver(string $entrada): string
    {
        if (! is_file($entrada) || ! is_readable($entrada)) {
            throw new RuntimeException("No se puede leer el archivo: {$entrada}");
        }

        return PadronIibbArchivoSupport::resolver($entrada, ['txt', 'csv']);
    }

    private function liberar(string $entrada, string $archivo): void
    {
        if ($archivo !== $entrada) {
            PadronIibbArchivoSupport::limpiarTemporal($archivo);
        }
    }
}
