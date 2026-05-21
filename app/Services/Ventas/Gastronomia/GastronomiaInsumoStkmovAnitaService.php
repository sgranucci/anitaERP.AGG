<?php

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Configuracion\Condicioniva;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Depmae;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Venta;
use App\Support\Ventas\AnitaStkmovPayloadSupport;
use App\Support\Ventas\GastronomiaDepositoConfigSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Replica en Informix (stkmov) las salidas de insumos por fórmula gastronomía.
 * El plato facturado ya viaja con grabaAnita; stkv_nro_orden del insumo =
 * (numeroitem del plato × 1000) + secuencia del insumo dentro de ese plato.
 */
final class GastronomiaInsumoStkmovAnitaService
{
    /** stkv_nro_orden insumo = (orden del plato en factura × 1000) + secuencia del insumo. */
    private const MULTIPLICADOR_ORDEN_PLATO = 1000;

    public function __construct(
        private readonly ApiAnita $apiAnita,
    ) {
    }

    public function replicarMovimientosInsumos(
        Venta $venta,
        ConfiguracionPuntoventaGastronomia $cfg,
        float $descuentoPie = 0.,
    ): void {
        if (! config('gastronomia.sincronizar_anita_al_facturar', true)) {
            return;
        }

        $movimientos = GastronomiaVentaDetalleSupport::movimientosInsumos((int) $venta->id);
        if ($movimientos->isEmpty()) {
            return;
        }

        $depositoInsumosId = GastronomiaDepositoConfigSupport::depositoInsumosId($cfg);
        $deposito = Depmae::query()->find($depositoInsumosId);
        $codigoDeposito = trim((string) ($deposito?->codigo ?? ''));
        if ($codigoDeposito === '') {
            throw new RuntimeException(
                'El depósito de insumos del PV gastronomía no tiene código Anita (depmae.codigo).'
            );
        }

        $venta->loadMissing([
            'puntoventas.empresas',
            'clientes',
            'tipotransacciones',
            'venta_emisiones',
        ]);

        $ctx = $this->resolverContextoComprobante($venta);

        /** @var array<int, int> venta_emision_id => numeroitem (stkv_nro_orden del plato en Anita) */
        $ordenPlatoPorEmision = $venta->venta_emisiones
            ->keyBy('id')
            ->map(fn ($em) => (int) $em->numeroitem)
            ->all();

        $movimientosOrdenados = $movimientos
            ->sortBy([
                ['venta_emision_id', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        /** @var array<int, int> venta_emision_id => último subíndice de insumo */
        $secuenciaInsumoPorEmision = [];

        foreach ($movimientosOrdenados as $movimiento) {
            if (! $movimiento instanceof Articulo_Movimiento) {
                continue;
            }
            $movimiento->loadMissing(['articulos.categorias']);
            $articulo = $movimiento->articulos;
            if (! $articulo) {
                continue;
            }

            $ventaEmisionId = (int) ($movimiento->venta_emision_id ?? 0);
            $ordenPlato = $ordenPlatoPorEmision[$ventaEmisionId] ?? 0;
            if ($ordenPlato <= 0) {
                throw new RuntimeException(
                    'No se pudo determinar el número de orden del plato (venta_emision_id '.$ventaEmisionId.') para replicar insumos en Anita.'
                );
            }

            $secuenciaInsumoPorEmision[$ventaEmisionId] = ($secuenciaInsumoPorEmision[$ventaEmisionId] ?? 0) + 1;
            $orden = ($ordenPlato * self::MULTIPLICADOR_ORDEN_PLATO) + $secuenciaInsumoPorEmision[$ventaEmisionId];

            $payload = AnitaStkmovPayloadSupport::payloadInsert($ctx, [
                'sku' => (string) $articulo->sku,
                'categoria_codigo' => (string) ($articulo->categorias->codigo ?? '0'),
                'cantidad' => abs((float) $movimiento->cantidad),
                'precio' => (float) $movimiento->precio,
                'impuesto_id' => (int) ($articulo->impuesto_id ?? 0),
                'incluyeimpuesto' => (string) ($movimiento->incluyeimpuesto ?? '1'),
                'deposito_codigo' => $codigoDeposito,
                'nro_orden' => $orden,
                'partida' => 0,
                'pedido' => 0,
            ], $descuentoPie);

            $respuesta = $this->apiAnita->apiCall($payload);
            $err = ApiAnita::extraerMensajeError($respuesta);
            if ($err !== null) {
                Log::warning('gastronomia.insumo_stkmov_anita.fallo', [
                    'venta_id' => $venta->id,
                    'venta_emision_id' => $ventaEmisionId,
                    'articulo_id' => $movimiento->articulo_id,
                    'sku' => $articulo->sku,
                    'orden_plato' => $ordenPlato,
                    'stkv_nro_orden' => $orden,
                    'msg' => $err,
                ]);

                throw new RuntimeException('Error replicando insumo en Anita (stkmov): '.$err);
            }
        }

        Log::info('gastronomia.insumo_stkmov_anita.ok', [
            'venta_id' => $venta->id,
            'lineas' => $movimientos->count(),
        ]);
    }

    /**
     * @return array{
     *   tipo:string,
     *   letra:string,
     *   puntoventa:string|int,
     *   numero:int|string,
     *   fecha:string,
     *   moneda_id:int|string,
     *   codigo_cliente:string|int,
     *   vendedor:int|string,
     *   zonavta_id:int|string,
     *   provincia_id:int|string,
     *   subzonavta_id:int|string,
     *   empresa_codigo?:string,
     * }
     */
    private function resolverContextoComprobante(Venta $venta): array
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if ($codigo === '') {
            throw new RuntimeException('La venta no tiene código de comprobante para replicar insumos en Anita.');
        }

        $pv = $venta->puntoventas;
        if (! $pv) {
            throw new RuntimeException('Punto de venta no encontrado para replicar insumos en Anita.');
        }

        $letra = 'B';
        if ($venta->condicioniva_id) {
            $condicion = Condicioniva::query()->find((int) $venta->condicioniva_id);
            if ($condicion && trim((string) ($condicion->letra ?? '')) !== '') {
                $letra = (string) $condicion->letra;
            }
        }

        $cliente = $venta->clientes;
        $codigoCliente = $cliente ? (string) ($cliente->codigo ?? '0') : '0';

        return [
            'tipo' => substr($codigo, 0, 3),
            'letra' => $letra,
            'puntoventa' => $pv->codigo,
            'numero' => $venta->numerocomprobante,
            'fecha' => (string) $venta->fecha,
            'moneda_id' => (int) ($venta->moneda_id ?? 1),
            'codigo_cliente' => $codigoCliente,
            'vendedor' => (int) ($cliente?->vendedor_id ?? 1),
            'zonavta_id' => $cliente?->zonavta_id ?? 0,
            'provincia_id' => $cliente?->provincia_id ?? 0,
            'subzonavta_id' => $cliente?->subzonavta_id ?? 0,
            'empresa_codigo' => (string) ($pv->empresas->codigo ?? ''),
        ];
    }
}
