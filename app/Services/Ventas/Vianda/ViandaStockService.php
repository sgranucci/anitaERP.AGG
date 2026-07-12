<?php

namespace App\Services\Ventas\Vianda;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Ventas\ConfiguracionTerminalVianda;
use App\Models\Ventas\ViandaConsumo;
use App\Services\Stock\Articulo_MovimientoService;
use App\Services\Ventas\Gastronomia\GastronomiaFormulaConsumoService;
use App\Support\Stock\ArticuloMovimientoPrecioHistoricoSupport;
use App\Support\Ventas\GastronomiaMovimientoStockSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;

/**
 * Descarga de stock ERP al marchar una vianda:
 *   - salida del plato/menú desde el depósito de platos de la terminal;
 *   - salida de cada insumo (explotando la fórmula del plato) desde el depósito de insumos.
 *
 * No replica movimientos a Anita: solo articulo_movimiento (venta_id nulo, sin factura).
 */
final class ViandaStockService
{
    public function __construct(
        private readonly Articulo_MovimientoService $articuloMovimientoService,
        private readonly GastronomiaFormulaConsumoService $formulaConsumoService,
    ) {
    }

    public function registrarConsumo(ViandaConsumo $consumo, ConfiguracionTerminalVianda $cfg): void
    {
        $tipo = Tipotransaccion_Stock::query()->find((int) $cfg->tipotransaccion_stock_id);
        if ($tipo === null || $tipo->estado !== 'A') {
            return;
        }

        $fecha = $consumo->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
        $fechaJornada = $consumo->fecha_jornada?->format('Y-m-d') ?? $fecha;
        $monedaId = (int) (config('gastronomia.moneda_factura_id') ?: 1);
        $depositoPlatos = (int) $cfg->deposito_platos_id;
        $depositoInsumos = (int) $cfg->deposito_insumos_id;
        $conceptoBase = $this->conceptoBase($consumo);

        $consumo->loadMissing('lineas.articulo');

        foreach ($consumo->lineas as $linea) {
            $articulo = $linea->articulo;
            if ($articulo === null) {
                continue;
            }

            $cantidad = (float) $linea->cantidad;
            if ($cantidad <= 0) {
                continue;
            }

            $payloadPlato = GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
                'fecha' => $fecha,
                'fechajornada' => $fechaJornada,
                'tipotransaccion_stock_id' => (int) $tipo->id,
                'articulo_id' => (int) $articulo->id,
                'concepto' => $conceptoBase,
                'cantidad' => $cantidad,
                'moneda_id' => $monedaId,
                'incluyeimpuesto' => 1,
                'deposito_id' => $depositoPlatos,
                'vianda_consumo_id' => (int) $consumo->id,
            ]);
            $precioCosto = (float) $linea->precio_costo_unitario;
            $payloadPlato['precio'] = round(max(0, $precioCosto), 6);
            $payloadPlato['costo'] = round(max(0, $precioCosto), 6);

            $this->persistir($payloadPlato);

            if (! $articulo->formula) {
                continue;
            }

            $insumos = $this->formulaConsumoService->insumosAgregadosPorArticulo((int) $articulo->id, $cantidad);
            if ($insumos === []) {
                continue;
            }

            $preciosInsumo = ArticuloMovimientoPrecioHistoricoSupport::resolverUltimaCompraInsumoPorArticuloIds(
                array_map('intval', array_keys($insumos))
            );

            foreach ($insumos as $ingArticuloId => $cantidadIng) {
                if ($cantidadIng <= 0) {
                    continue;
                }

                $payloadInsumo = GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
                    'fecha' => $fecha,
                    'fechajornada' => $fechaJornada,
                    'tipotransaccion_stock_id' => (int) $tipo->id,
                    'articulo_id' => (int) $ingArticuloId,
                    'concepto' => $conceptoBase.GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO,
                    'cantidad' => $cantidadIng,
                    'moneda_id' => $monedaId,
                    'incluyeimpuesto' => 1,
                    'deposito_id' => $depositoInsumos,
                    'vianda_consumo_id' => (int) $consumo->id,
                ]);

                $datoPrecio = $preciosInsumo[(int) $ingArticuloId] ?? null;
                if ($datoPrecio !== null) {
                    $payloadInsumo['precio'] = $datoPrecio['precio'];
                    $payloadInsumo['costo'] = $datoPrecio['costo'];
                    if (! empty($datoPrecio['moneda_id'])) {
                        $payloadInsumo['moneda_id'] = $datoPrecio['moneda_id'];
                    }
                }

                $this->persistir($payloadInsumo);
            }
        }
    }

    /**
     * Reversa (devuelve) el stock descargado al marchar la vianda: crea movimientos
     * compensatorios con la cantidad de signo opuesto sobre cada movimiento original.
     *
     * Localiza los movimientos originales por el concepto ("Vianda {codigo_retiro}…"),
     * que es único por consumo, de modo que la reversa es exacta (mismo depósito, artículo
     * y cantidad) y no depende de que la terminal/fórmula sigan existiendo.
     */
    public function revertirConsumo(ViandaConsumo $consumo): void
    {
        // Relación dura: los movimientos originales apuntan a la vianda por vianda_consumo_id.
        // Se excluyen las reversas previas ("Reversa …") para que la operación sea idempotente.
        $movimientos = Articulo_Movimiento::query()
            ->where('vianda_consumo_id', (int) $consumo->id)
            ->where('concepto', 'not like', 'Reversa %')
            ->get();

        if ($movimientos->isEmpty()) {
            // Fallback para consumos anteriores a la FK dura (vinculados solo por concepto único).
            $codigo = trim((string) $consumo->codigo_retiro);
            if ($codigo === '') {
                return;
            }

            $movimientos = Articulo_Movimiento::query()
                ->whereNull('vianda_consumo_id')
                ->where('concepto', 'like', 'Vianda '.$this->escaparLike($codigo).'%')
                ->get();
        }

        foreach ($movimientos as $mov) {
            $cantidadOriginal = (float) $mov->cantidad;
            if ($cantidadOriginal == 0.0) {
                continue;
            }

            $base = [
                'fecha' => now()->format('Y-m-d'),
                'fechajornada' => $consumo->fecha_jornada?->format('Y-m-d')
                    ?? ((string) ($mov->fechajornada ?? '') ?: now()->format('Y-m-d')),
                'articulo_id' => (int) $mov->articulo_id,
                'combinacion_id' => $mov->combinacion_id,
                'concepto' => mb_substr('Reversa '.(string) $mov->concepto, 0, 200),
                'moneda_id' => (int) ($mov->moneda_id ?: 1),
                'incluyeimpuesto' => $mov->incluyeimpuesto ?? 1,
                'deposito_id' => (int) $mov->deposito_id,
                'precio' => (string) ($mov->precio ?? '0'),
                'costo' => (float) ($mov->costo ?? 0),
                'vianda_consumo_id' => (int) $consumo->id,
            ];

            if ((int) $mov->tipotransaccion_stock_id > 0) {
                $base['tipotransaccion_stock_id'] = (int) $mov->tipotransaccion_stock_id;
            } else {
                $base['tipotransaccion_id'] = (int) $mov->tipotransaccion_id;
            }

            $payload = GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento($base);
            // Cantidad ya firmada: opuesta a la original (devuelve lo que la salida restó).
            $payload['cantidad'] = -1 * $cantidadOriginal;
            $payload['cantidad_ya_firmada'] = true;

            $this->persistir($payload);
        }
    }

    private function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistir(array $data): void
    {
        // El signo lo aplica guardaArticuloMovimiento según el signo del tipo de
        // transacción de stock resuelto por tipotransaccion_stock_id (salida = resta),
        // salvo cuando el payload trae cantidad_ya_firmada (reversa) y se usa tal cual.
        $this->articuloMovimientoService->guardaArticuloMovimiento('create', $data, []);
    }

    private function conceptoBase(ViandaConsumo $consumo): string
    {
        $codigo = trim((string) $consumo->codigo_retiro);
        $usuario = trim((string) $consumo->nombre_usuario);
        $concepto = 'Vianda '.$codigo.($usuario !== '' ? ' - '.$usuario : '');

        return mb_substr($concepto, 0, 200);
    }
}
