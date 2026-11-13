<?php

declare(strict_types=1);

namespace App\Support\Ventas\Ferli;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\Tipotransaccion;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ferli: NC de mostrador sin factura origen.
 * Exige combinación/talle en las líneas de mercadería y genera entrada de stock
 * (alta producción), porque NCD tiene operacionstock = O y no mueve stock genérico.
 *
 * Sin lote en la línea, asigna un correlativo de la serie de lotes de stock
 * (misma numeración que el alta de MovimientoStockService: id + 500001).
 *
 * No usar cuando la emisión ya movió stock (PV de local fuerza ENTRADA sobre NCD):
 * ahí el movimiento queda como NCD "Credito" y un ALTAP extra duplicaría el par.
 */
final class NotaCreditoSinFacturaStockFerliSupport
{
    public const MODULO_ABIERTO_ID = 30;

    public const CONCEPTO_DEVOLUCION_NC = 'Devolución NC';

    /** Misma base que MovimientoStockService::asignarNumeroLoteAlta. */
    private const LOTE_STOCK_OFFSET_ALTA = 500001;

    private const LOTE_STOCK_RANGO_MIN = 500000;

    private const LOTE_STOCK_RANGO_MAX = 999999;

    /**
     * @param  list<array<string, mixed>>  $dataFactura
     * @param  array<string, mixed>  $requestData
     * @return array{error: string}|null
     */
    public static function validarLineas(array $dataFactura, array $requestData): ?array
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return null;
        }

        if (! self::esNcSinOrigen($requestData)) {
            return null;
        }

        foreach ($dataFactura as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            if ((int) ($item['pedido_combinacion_id'] ?? 0) > 0) {
                continue;
            }

            $combinacionId = (int) ($item['combinacion_id'] ?? 0);
            $talleId = (int) ($item['talle_id'] ?? 0);
            if ($combinacionId <= 0 || $talleId <= 0) {
                $renglon = $i + 1;
                $sku = trim((string) ($item['sku'] ?? ''));
                $detalle = $sku !== '' ? " (SKU {$sku})" : '';

                return [
                    'error' => "Nota de crédito sin factura: el renglón {$renglon}{$detalle} debe tener combinación y talle. Usá el botón de variante (cubos) en la línea.",
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $dataFactura
     */
    public static function alGrabarNcSinOrigen(
        int $ventaNcId,
        array $dataFactura,
        string $fechaYmd,
        int $depositoId,
        int $ventaOrigenId = 0
    ): void {
        if (! EntornoEmpresaSupport::esFerli() || $ventaNcId <= 0) {
            return;
        }

        if ($ventaOrigenId > 0) {
            return;
        }

        foreach ($dataFactura as $item) {
            if (! is_array($item)) {
                continue;
            }
            if ((int) ($item['pedido_combinacion_id'] ?? 0) > 0) {
                return;
            }
        }

        $grupos = self::agruparLineas($dataFactura);
        if ($grupos === []) {
            return;
        }

        if ($depositoId <= 0) {
            $depositoId = 1;
        }

        $fecha = substr(trim($fechaYmd), 0, 10);
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $servicio = app(Articulo_MovimientoService::class);
        $creados = 0;
        $proximoLoteStock = null;
        $lotesAsignados = [];

        foreach ($grupos as $grupo) {
            $articuloId = (int) $grupo['articulo_id'];
            $combinacionId = (int) $grupo['combinacion_id'];
            if ($articuloId <= 0 || $combinacionId <= 0 || $grupo['talles'] === []) {
                continue;
            }

            $lote = trim((string) ($grupo['lote'] ?? ''));
            if ($lote === '' || $lote === '0') {
                if ($proximoLoteStock === null) {
                    $proximoLoteStock = self::siguienteLoteStockCorrelativo();
                }
                $lote = (string) $proximoLoteStock;
                $proximoLoteStock++;
            }

            if (self::yaTieneMovimiento($ventaNcId, $articuloId, $combinacionId, $lote)) {
                continue;
            }

            $cantidad = 0.0;
            $dataTalle = [];
            $precioCabecera = 0.0;
            foreach ($grupo['talles'] as $talle) {
                $cant = abs((float) ($talle['cantidad'] ?? 0));
                if ($cant <= 0.0001) {
                    continue;
                }
                $precio = (float) ($talle['precio'] ?? 0);
                if ($precioCabecera <= 0.0 && $precio > 0.0) {
                    $precioCabecera = $precio;
                }
                $dataTalle[] = [
                    'id' => null,
                    'talle_id' => (int) ($talle['talle_id'] ?? 0),
                    'cantidad' => $cant,
                    'precio' => $precio,
                ];
                $cantidad += $cant;
            }
            if ($cantidad <= 0.0001 || $dataTalle === []) {
                continue;
            }

            $moduloId = (int) ($grupo['modulo_id'] ?? 0);
            if ($moduloId <= 0) {
                $moduloId = self::MODULO_ABIERTO_ID;
            }

            $servicio->guardaArticuloMovimiento('create', [
                'fecha' => $fecha,
                'fechajornada' => $fecha,
                'tipotransaccion_id' => config('consprod.TIPOTRANSACCION_ALTA_PRODUCCION'),
                'venta_id' => $ventaNcId,
                'lote' => $lote,
                'articulo_id' => $articuloId,
                'combinacion_id' => $combinacionId,
                'modulo_id' => $moduloId,
                'concepto' => self::CONCEPTO_DEVOLUCION_NC,
                'cantidad' => $cantidad,
                'precio' => $precioCabecera,
                'costo' => 0,
                'descuento' => $grupo['descuento'] ?? 0,
                'descuentointegrado' => $grupo['descuentointegrado'] ?? 0,
                'moneda_id' => $grupo['moneda_id'] ?? null,
                'incluyeimpuesto' => $grupo['incluyeimpuesto'] ?? '',
                'listaprecio_id' => $grupo['listaprecio_id'] ?? null,
                'deposito_id' => $depositoId,
            ], $dataTalle);
            $creados++;
            $lotesAsignados[] = $lote;
        }

        if ($creados > 0) {
            try {
                Log::info('ferli.nc.sin_factura_stock', [
                    'venta_nc_id' => $ventaNcId,
                    'movimientos' => $creados,
                    'deposito_id' => $depositoId,
                    'lotes' => $lotesAsignados,
                ]);
            } catch (\Throwable) {
                // No tumbar la NC por fallo de log.
            }
        }
    }

    /**
     * Correlativo de lotes de stock Ferli (serie de alta producción / TRA).
     * Usa el máximo entre la fórmula histórica (último movimientostock + 500001)
     * y el mayor lote ya grabado en ese rango, para no chocar con altas previas.
     */
    public static function siguienteLoteStockCorrelativo(): int
    {
        $porFormula = (int) (DB::table('movimientostock')->max('id') ?? 0) + self::LOTE_STOCK_OFFSET_ALTA;
        $maxExistente = (int) (Articulo_Movimiento::query()
            ->whereBetween('lote', [self::LOTE_STOCK_RANGO_MIN, self::LOTE_STOCK_RANGO_MAX])
            ->max('lote') ?? 0);

        return max($porFormula, $maxExistente + 1);
    }

    /**
     * @param  array<string, mixed>  $requestData
     */
    public static function esNcSinOrigen(array $requestData): bool
    {
        if ((int) ($requestData['venta_id'] ?? 0) > 0) {
            return false;
        }

        $tipoId = (int) ($requestData['tipotransaccion_id'] ?? 0);
        if ($tipoId <= 0) {
            return false;
        }

        $tipo = Tipotransaccion::query()->find($tipoId);
        if (! $tipo || ! method_exists($tipo, 'esNotaCredito') || ! $tipo->esNotaCredito()) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $dataFactura
     * @return list<array{
     *   articulo_id: int,
     *   combinacion_id: int,
     *   modulo_id: int,
     *   lote: string,
     *   descuento: mixed,
     *   descuentointegrado: mixed,
     *   moneda_id: mixed,
     *   incluyeimpuesto: mixed,
     *   listaprecio_id: mixed,
     *   talles: list<array{talle_id: int, cantidad: float, precio: float}>
     * }>
     */
    private static function agruparLineas(array $dataFactura): array
    {
        $grupos = [];

        foreach ($dataFactura as $item) {
            if (! is_array($item)) {
                continue;
            }
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            $combinacionId = (int) ($item['combinacion_id'] ?? 0);
            $talleId = (int) ($item['talle_id'] ?? 0);
            $cantidad = abs((float) ($item['cantidad'] ?? 0));
            if ($articuloId <= 0 || $combinacionId <= 0 || $talleId <= 0 || $cantidad <= 0.0001) {
                continue;
            }

            $moduloId = (int) ($item['modulo_id'] ?? 0);
            if ($moduloId <= 0) {
                $moduloId = self::MODULO_ABIERTO_ID;
            }
            $lote = trim((string) ($item['lote_stock'] ?? $item['lote'] ?? ''));
            if ($lote === '0') {
                $lote = '';
            }
            $clave = $articuloId.'|'.$combinacionId.'|'.$moduloId.'|'.$lote;

            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'articulo_id' => $articuloId,
                    'combinacion_id' => $combinacionId,
                    'modulo_id' => $moduloId,
                    'lote' => $lote,
                    'descuento' => $item['descuento'] ?? 0,
                    'descuentointegrado' => $item['descuentointegrado'] ?? 0,
                    'moneda_id' => $item['moneda_id'] ?? null,
                    'incluyeimpuesto' => $item['incluyeimpuesto'] ?? '',
                    'listaprecio_id' => $item['listaprecio_id'] ?? null,
                    'talles' => [],
                ];
            }

            $grupos[$clave]['talles'][] = [
                'talle_id' => $talleId,
                'cantidad' => $cantidad,
                'precio' => (float) ($item['precio'] ?? 0),
            ];
        }

        return array_values($grupos);
    }

    private static function yaTieneMovimiento(
        int $ventaNcId,
        int $articuloId,
        int $combinacionId,
        string $lote
    ): bool {
        // Cualquier entrada ya grabada para esta NC (Credito forzado en local u otro ALTAP).
        $q = Articulo_Movimiento::query()
            ->where('venta_id', $ventaNcId)
            ->where('articulo_id', $articuloId)
            ->where('combinacion_id', $combinacionId)
            ->where('cantidad', '>', 0);

        if ($lote !== '') {
            $q->where(function ($inner) use ($lote) {
                $inner->where('lote', $lote)
                    ->orWhere('concepto', self::CONCEPTO_DEVOLUCION_NC);
            });
        }

        return $q->exists();
    }
}
