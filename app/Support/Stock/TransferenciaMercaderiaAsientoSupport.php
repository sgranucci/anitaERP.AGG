<?php

namespace App\Support\Stock;

use App\Models\Contable\Tipoasiento;
use App\Models\Stock\Articulo;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Stock\Transferencia_Mercaderia_Articulo;
use App\Repositories\Contable\TipoasientoRepositoryInterface;

/**
 * Arma el preview del asiento contable de transferencia (legacy ASIST_arma_contabilidad).
 */
final class TransferenciaMercaderiaAsientoSupport
{
    /**
     * @return array{
     *     total_movimiento: float,
     *     total_debe: float,
     *     total_haber: float,
     *     payload_asiento: array<string, mixed>,
     *     advertencias: list<string>
     * }
     */
    public static function armarPreview(
        Transferencia_Mercaderia $transferencia,
        TipoasientoRepositoryInterface $tipoasientoRepository,
    ): array {
        $transferencia->loadMissing([
            'articulos.articuloOrigen.articulo_cuentacontables',
            'tipotransaccion_stock',
        ]);

        $empresaId = (int) $transferencia->empresa_id;
        $ccDestinoId = (int) ($transferencia->centrocosto_destino_id ?? 0);
        if ($ccDestinoId <= 0) {
            throw new \RuntimeException('Falta centro de costo destino para generar el asiento contable de la transferencia.');
        }

        $articulosOrigen = $transferencia->articulos
            ->map(fn (Transferencia_Mercaderia_Articulo $l) => $l->articuloOrigen)
            ->filter()
            ->unique('id');

        $preciosPorArticulo = ArticuloPrecioTransferenciaContableSupport::resolverPreciosPorArticulos($articulosOrigen);

        $monedaId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
        $tipoAsiento = self::resolverTipoAsientoStock($tipoasientoRepository);

        $clave = self::parsearClaveComprobante((string) $transferencia->codigo);

        $payloadAsiento = [
            'empresa_id' => $empresaId,
            'tipoasiento_id' => $tipoAsiento->id,
            'fecha' => $transferencia->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'movimientostock_id' => (int) ($transferencia->movimientostock_entrada_id ?? $transferencia->movimientostock_salida_id ?? 0) ?: null,
            'observacion' => 'Transferencia '.$transferencia->codigo,
            'tipo' => $clave['tipo'],
            'letra' => $clave['letra'],
            'sucursal' => $clave['sucursal'],
            'nro' => $clave['nro'],
            'sistema_ctav' => 'S',
            'moneda_ids' => [],
            'centrocosto_ids' => [],
            'cuentacontable_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
        ];

        /** @var array<string, array{importe: float, cc_id: int, obs: string}> */
        $debeAgrupado = [];
        /** @var array<string, array{importe: float, cc_id: int, obs: string}> */
        $haberAgrupado = [];
        $advertencias = [];
        $totalMovimiento = 0.0;

        foreach ($transferencia->articulos as $linea) {
            $articulo = $linea->articuloOrigen;
            if (! $articulo instanceof Articulo) {
                continue;
            }

            $articuloId = (int) $articulo->id;
            $precio = $preciosPorArticulo[$articuloId] ?? null;
            if ($precio === null || $precio <= 0) {
                $modo = ArticuloPrecioTransferenciaContableSupport::usaPrecioPromedio($articulo)
                    ? 'promedio de últimas compras'
                    : 'última compra';
                $advertencias[] = 'Artículo '.$articulo->sku.': sin precio de '.$modo.' para contabilidad.';

                continue;
            }

            $cantidad = (float) $linea->cantidad_origen;
            if ($cantidad <= 0) {
                continue;
            }

            $importe = round($cantidad * $precio, 2);
            if ($importe <= 0) {
                continue;
            }
            $totalMovimiento += $importe;

            $cuentaGastoId = self::resolverCuentaGastoId($articulo, $empresaId);
            $cuentaContrapartidaId = (int) ($articulo->cuentacontablecompra_id ?? 0);
            $ccCompraId = (int) ($articulo->centrocostocompra_id ?? 0);

            if ($cuentaGastoId <= 0 || $cuentaContrapartidaId <= 0) {
                $advertencias[] = 'Artículo '.$articulo->sku.': faltan cuentas contables (gasto y/o compra).';

                continue;
            }

            if ($cuentaGastoId === $cuentaContrapartidaId) {
                continue;
            }

            $obs = trim($articulo->sku.' x '.$cantidad);

            $claveDebe = $cuentaGastoId.'|'.$ccDestinoId;
            if (! isset($debeAgrupado[$claveDebe])) {
                $debeAgrupado[$claveDebe] = [
                    'cuenta_id' => $cuentaGastoId,
                    'importe' => 0.0,
                    'cc_id' => $ccDestinoId,
                    'obs' => $obs,
                ];
            }
            $debeAgrupado[$claveDebe]['importe'] += $importe;

            $claveHaber = $cuentaContrapartidaId.'|'.$ccCompraId;
            if (! isset($haberAgrupado[$claveHaber])) {
                $haberAgrupado[$claveHaber] = [
                    'cuenta_id' => $cuentaContrapartidaId,
                    'importe' => 0.0,
                    'cc_id' => $ccCompraId,
                    'obs' => $obs,
                ];
            }
            $haberAgrupado[$claveHaber]['importe'] += $importe;
        }

        if ($debeAgrupado === [] && $haberAgrupado === []) {
            $msg = 'No hay líneas contables para la transferencia.';
            if ($advertencias !== []) {
                $msg .= ' '.implode(' ', $advertencias);
            }
            throw new \RuntimeException($msg);
        }

        foreach ($debeAgrupado as $grupo) {
            $payloadAsiento['cuentacontable_ids'][] = $grupo['cuenta_id'];
            $payloadAsiento['moneda_ids'][] = $monedaId;
            $payloadAsiento['centrocosto_ids'][] = $grupo['cc_id'] > 0 ? $grupo['cc_id'] : null;
            $payloadAsiento['debes'][] = round($grupo['importe'], 2);
            $payloadAsiento['haberes'][] = 0;
            $payloadAsiento['cotizaciones'][] = 1;
            $payloadAsiento['observaciones'][] = $grupo['obs'];
        }

        foreach ($haberAgrupado as $grupo) {
            $payloadAsiento['cuentacontable_ids'][] = $grupo['cuenta_id'];
            $payloadAsiento['moneda_ids'][] = $monedaId;
            $payloadAsiento['centrocosto_ids'][] = $grupo['cc_id'] > 0 ? $grupo['cc_id'] : null;
            $payloadAsiento['debes'][] = 0;
            $payloadAsiento['haberes'][] = round($grupo['importe'], 2);
            $payloadAsiento['cotizaciones'][] = 1;
            $payloadAsiento['observaciones'][] = $grupo['obs'];
        }

        $totalDebe = round(array_sum($payloadAsiento['debes']), 2);
        $totalHaber = round(array_sum($payloadAsiento['haberes']), 2);

        if (abs($totalDebe - $totalHaber) >= 0.01) {
            throw new \RuntimeException(
                'El asiento de transferencia no cuadra (debe '.$totalDebe.' ≠ haber '.$totalHaber.').'
            );
        }

        return [
            'total_movimiento' => round($totalMovimiento, 2),
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'payload_asiento' => $payloadAsiento,
            'advertencias' => $advertencias,
        ];
    }

    private static function resolverCuentaGastoId(Articulo $articulo, int $empresaId): int
    {
        $cuentaGrid = $articulo->articulo_cuentacontables
            ?->first(fn ($row) => (int) $row->empresa_id === $empresaId
                && strtoupper((string) $row->tipoimputacion) === 'GASTOS');

        if ($cuentaGrid && (int) $cuentaGrid->cuentacontable_id > 0) {
            return (int) $cuentaGrid->cuentacontable_id;
        }

        return (int) ($articulo->cuentacontablecompra_id ?? 0);
    }

    private static function resolverTipoAsientoStock(TipoasientoRepositoryInterface $repo): Tipoasiento
    {
        $tipo = $repo->findPorAbreviatura('STK') ?? $repo->findPorAbreviatura('COM');
        if ($tipo instanceof Tipoasiento) {
            return $tipo;
        }

        return $repo->create([
            'nombre' => 'Stock',
            'abreviatura' => 'STK',
        ]);
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    private static function parsearClaveComprobante(string $codigo): array
    {
        $nro = 0;
        if (preg_match('/(\d{6,})$/', $codigo, $m)) {
            $nro = (int) substr($m[1], -8);
        }

        return [
            'tipo' => 'TRA',
            'letra' => ' ',
            'sucursal' => 0,
            'nro' => $nro,
        ];
    }
}
