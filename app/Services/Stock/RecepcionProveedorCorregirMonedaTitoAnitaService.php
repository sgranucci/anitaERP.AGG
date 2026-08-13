<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use InvalidArgumentException;

/**
 * Alinea moneda/cotización de líneas TITO con Anita recepmov.
 *
 * Solo agosto 2026 en adelante. No toca julio 2026 ni anteriores.
 */
class RecepcionProveedorCorregirMonedaTitoAnitaService
{
    public const FECHA_PISO = '2026-08-01';

    /**
     * @return array{
     *     candidatas: int,
     *     lineas_revisadas: int,
     *     lineas_actualizadas: int,
     *     cabeceras_actualizadas: int,
     *     sin_recepmov: int,
     *     sin_linea_anita: int,
     *     omitidas_julio: int,
     *     sin_cambio: int,
     *     errores: int
     * }
     */
    public function ejecutar(
        string $fechaDesde,
        string $fechaHasta,
        bool $dryRun = false,
        ?callable $onError = null,
    ): array {
        $fechaDesde = $this->normalizarFecha($fechaDesde);
        $fechaHasta = $this->normalizarFecha($fechaHasta);
        if ($fechaDesde < self::FECHA_PISO) {
            throw new InvalidArgumentException(
                'No se puede corregir COM anteriores a '.self::FECHA_PISO.' (julio queda intacto).'
            );
        }
        if ($fechaHasta < $fechaDesde) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $stats = [
            'candidatas' => 0,
            'lineas_revisadas' => 0,
            'lineas_actualizadas' => 0,
            'cabeceras_actualizadas' => 0,
            'sin_recepmov' => 0,
            'sin_linea_anita' => 0,
            'omitidas_julio' => 0,
            'sin_cambio' => 0,
            'errores' => 0,
        ];

        $articuloIdsTito = Articulo::query()
            ->where('fl_precio_promedio_transferencia', 1)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($articuloIdsTito === []) {
            return $stats;
        }

        $query = Recepcion_Proveedor::query()
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->whereDate('fecha', '>=', self::FECHA_PISO)
            ->whereHas('recepcion_proveedor_articulos', static function ($q) use ($articuloIdsTito) {
                $q->whereIn('articulo_id', $articuloIdsTito);
            })
            ->orderBy('id');

        $stats['candidatas'] = (clone $query)->count();

        $query->with([
            'recepcion_proveedor_articulos.articulos:id,sku,fl_precio_promedio_transferencia',
        ])->chunkById(50, function ($recepciones) use (
            $dryRun,
            $articuloIdsTito,
            $onError,
            &$stats
        ) {
            foreach ($recepciones as $recepcion) {
                try {
                    $this->corregirRecepcion($recepcion, $articuloIdsTito, $dryRun, $stats);
                } catch (\Throwable $e) {
                    $stats['errores']++;
                    if ($onError !== null) {
                        $onError($recepcion, $e);
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * @param  list<int>  $articuloIdsTito
     * @param  array<string, int>  $stats
     */
    private function corregirRecepcion(
        Recepcion_Proveedor $recepcion,
        array $articuloIdsTito,
        bool $dryRun,
        array &$stats,
    ): void {
        $fecha = $recepcion->fecha?->format('Y-m-d') ?? '';
        if ($fecha !== '' && $fecha < self::FECHA_PISO) {
            $stats['omitidas_julio']++;

            return;
        }

        $tipo = trim((string) ($recepcion->anita_tipo ?? 'COM')) ?: 'COM';
        $letra = trim((string) ($recepcion->anita_letra ?? 'X')) ?: 'X';
        $sucursal = (int) ($recepcion->anita_sucursal ?? 0);
        $nro = (int) ($recepcion->anita_nro ?? $recepcion->numerorecepcion ?? 0);
        if ($sucursal <= 0 || $nro <= 0) {
            throw new \RuntimeException('Recepción sin clave Anita.');
        }

        $lineasAnita = RecepcionProveedorAnitaImportSupport::listarRecepmov($tipo, $letra, $sucursal, $nro);
        if ($lineasAnita === []) {
            $stats['sin_recepmov']++;

            return;
        }

        $titoIds = array_fill_keys($articuloIdsTito, true);
        $lineasTito = [];
        $todasSonTito = true;
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $articuloId = (int) $linea->articulo_id;
            if (! isset($titoIds[$articuloId])) {
                $todasSonTito = false;

                continue;
            }
            $lineasTito[] = $linea;
        }

        $monedaCabeceraDestino = null;
        $cotizacionCabeceraDestino = null;

        foreach ($lineasTito as $linea) {
            $stats['lineas_revisadas']++;
            $sku = trim((string) ($linea->articulos->sku ?? ''));
            if ($sku === '') {
                $stats['sin_linea_anita']++;

                continue;
            }

            $linAnita = RecepcionProveedorAnitaImportSupport::lineaRecepmovPorSku($lineasAnita, $sku);
            if ($linAnita === null) {
                $stats['sin_linea_anita']++;

                continue;
            }

            $monedaId = RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($linAnita->recv_cod_mon ?? 1);
            $cotizacion = (float) ($linAnita->recv_cotizacion ?? 1) ?: 1.0;
            $monedaCabeceraDestino = $monedaId;
            $cotizacionCabeceraDestino = $cotizacion;

            $monedaActual = (int) ($linea->moneda_id ?? 0);
            $cotizacionActual = (float) ($linea->cotizacion ?? 0);
            if ($monedaActual === $monedaId && abs($cotizacionActual - $cotizacion) < 0.000001) {
                $stats['sin_cambio']++;

                continue;
            }

            if (! $dryRun) {
                Recepcion_Proveedor_Articulo::query()
                    ->whereKey($linea->id)
                    ->update([
                        'moneda_id' => $monedaId,
                        'cotizacion' => $cotizacion,
                    ]);
            }
            $stats['lineas_actualizadas']++;
        }

        if (! $todasSonTito || $monedaCabeceraDestino === null) {
            return;
        }

        $cabMoneda = (int) ($recepcion->moneda_id ?? 0);
        $cabCot = (float) ($recepcion->cotizacion ?? 0);
        if ($cabMoneda === $monedaCabeceraDestino && abs($cabCot - (float) $cotizacionCabeceraDestino) < 0.000001) {
            return;
        }

        if (! $dryRun) {
            Recepcion_Proveedor::query()
                ->whereKey($recepcion->id)
                ->update([
                    'moneda_id' => $monedaCabeceraDestino,
                    'cotizacion' => $cotizacionCabeceraDestino,
                ]);
        }
        $stats['cabeceras_actualizadas']++;
    }

    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha inválida: '.$fecha.' (usar YYYY-MM-DD).');
        }

        return $fecha;
    }
}
