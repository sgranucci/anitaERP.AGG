<?php

namespace App\Support\Stock;

use App\Models\Contable\Tipoasiento;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Contable\TipoasientoRepositoryInterface;

/**
 * Arma el preview del asiento contable de movimientos de stock (legacy ASIST_arma_contabilidad).
 */
final class MovimientoStockAsientoSupport
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
        MovimientoStock $movimiento,
        TipoasientoRepositoryInterface $tipoasientoRepository,
    ): array {
        $movimiento->loadMissing([
            'articulos_movimiento.articulos.articulo_cuentacontables',
            'tipotransaccion_stock',
        ]);

        $tipo = $movimiento->tipotransaccion_stock;
        if (! $tipo instanceof Tipotransaccion_Stock || ! (bool) $tipo->maneja_contabilidad) {
            throw new \RuntimeException('El tipo de transacción no genera asiento contable.');
        }

        $empresaId = self::resolverEmpresaId($movimiento);
        $ccDestinoId = (int) ($movimiento->centrocosto_destino_id ?? 0);
        if ($ccDestinoId <= 0) {
            throw new \RuntimeException('Debe indicar centro de costo destino para el asiento contable.');
        }

        $articulos = $movimiento->articulos_movimiento
            ->map(fn (Articulo_Movimiento $l) => $l->articulos)
            ->filter()
            ->unique('id');

        $preciosPorArticulo = ArticuloPrecioTransferenciaContableSupport::resolverPreciosPorArticulos($articulos);

        $monedaId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
        $tipoAsiento = self::resolverTipoAsientoStock($tipoasientoRepository);
        $clave = self::parsearClaveComprobante((string) $movimiento->codigo, $tipo);

        $payloadAsiento = [
            'empresa_id' => $empresaId,
            'tipoasiento_id' => $tipoAsiento->id,
            'fecha' => self::fechaIso($movimiento->fecha),
            'movimientostock_id' => (int) ($movimiento->id ?? 0) ?: null,
            'observacion' => 'Movimiento stock '.trim((string) $movimiento->codigo),
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

        $debeAgrupado = [];
        $haberAgrupado = [];
        $advertencias = [];
        $totalMovimiento = 0.0;

        foreach ($movimiento->articulos_movimiento as $linea) {
            $articulo = $linea->articulos;
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

            $cantidad = abs((float) $linea->cantidad);
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
            $msg = 'No hay líneas contables para el movimiento de stock.';
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
                'El asiento no cuadra (debe '.$totalDebe.' ≠ haber '.$totalHaber.').'
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

    public static function resolverEmpresaId(MovimientoStock $movimiento): int
    {
        $depositoId = (int) ($movimiento->articulos_movimiento->first()->deposito_id ?? 0);
        if ($depositoId > 0) {
            $empresaId = (int) (Depmae::query()->whereKey($depositoId)->value('empresa_id') ?? 0);
            if ($empresaId > 0) {
                return $empresaId;
            }
        }

        throw new \RuntimeException('No se pudo determinar la empresa del movimiento de stock.');
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
    private static function parsearClaveComprobante(string $codigo, Tipotransaccion_Stock $tipo): array
    {
        $nro = is_numeric($codigo) ? (int) $codigo : 0;
        if ($nro === 0 && preg_match('/(\d+)/', $codigo, $m)) {
            $nro = (int) $m[1];
        }

        $abrev = strtoupper(substr(trim((string) ($tipo->abreviatura ?? 'STK')), 0, 3));

        return [
            'tipo' => $abrev !== '' ? $abrev : 'STK',
            'letra' => ' ',
            'sucursal' => 0,
            'nro' => $nro,
        ];
    }

    private static function fechaIso(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        return substr((string) ($fecha ?? now()->format('Y-m-d')), 0, 10);
    }
}
