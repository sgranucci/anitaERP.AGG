<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Asiento_Movimiento;
use App\Support\Stock\RecepcionProveedorAsientoAuditoriaSupport;
use App\Support\Ventas\IvaVentas\IvaVentasConciliacionCuentaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Totales contables de ventas (cuentas gravadas/kiosco/IVA) en ERP y ctamov Anita.
 */
final class GastronomiaFacturacionAuditoriaCtamovSupport
{
    /**
     * @return array{
     *   cuenta_ids: list<int>,
     *   codigos_cuenta: list<int>
     * }
     */
    public static function cuentasVentasConciliacion(int $empresaId): array
    {
        $cfg = IvaVentasConciliacionCuentaSupport::cuentasConciliacionEmpresa($empresaId);
        $ids = array_values(array_unique(array_merge(
            $cfg['ventas_gravadas'] ?? [],
            $cfg['ventas_kiosco'] ?? [],
            $cfg['iva_debito'] ?? [],
            $cfg['percepcion_iva'] ?? [],
        )));

        $codigos = [];
        foreach ($cfg['detalle'] ?? [] as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0 || ! in_array($id, $ids, true)) {
                continue;
            }
            $codigo = (int) ($item['codigo'] ?? 0);
            if ($codigo > 0) {
                $codigos[] = $codigo;
            }
        }

        if ($codigos === [] && $ids !== []) {
            $codigos = DB::table('cuentacontable')
                ->whereIn('id', $ids)
                ->pluck('codigo')
                ->map(static fn ($c) => (int) $c)
                ->filter(static fn (int $c) => $c > 0)
                ->values()
                ->all();
        }

        return [
            'cuenta_ids' => $ids,
            'codigos_cuenta' => array_values(array_unique($codigos)),
        ];
    }

    /**
     * @param  list<int>  $ventaIds
     * @param  list<int>  $cuentaIds
     */
    public static function totalContableErpVentas(int $empresaId, array $ventaIds, array $cuentaIds): float
    {
        if ($ventaIds === [] || $cuentaIds === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach (array_chunk($ventaIds, 2000) as $chunk) {
            $rows = DB::table('asiento as a')
                ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
                ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
                ->where('a.empresa_id', $empresaId)
                ->whereIn('a.venta_id', $chunk)
                ->whereIn('cc.id', $cuentaIds)
                ->selectRaw('SUM(-am.monto) as importe')
                ->value('importe');

            $total += (float) ($rows ?? 0);
        }

        return round($total, 2);
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array{con_asiento: int, sin_asiento: int}
     */
    public static function statsAsientoPorVentas(array $ventaIds): array
    {
        if ($ventaIds === []) {
            return ['con_asiento' => 0, 'sin_asiento' => 0];
        }

        $con = [];
        foreach (array_chunk($ventaIds, 2000) as $chunk) {
            foreach (DB::table('asiento')->whereIn('venta_id', $chunk)->pluck('venta_id') as $id) {
                $con[(int) $id] = true;
            }
        }

        $conAsiento = count($con);

        return [
            'con_asiento' => $conAsiento,
            'sin_asiento' => max(0, count($ventaIds) - $conAsiento),
        ];
    }

    /**
     * @param  list<int>  $ventaIds
     * @param  list<int>  $codigosCuenta
     * @return array{
     *   total_ctamov: float,
     *   asientos_consultados: int,
     *   sin_ctamov: int,
     *   dif_lineas: int,
     *   ok_lineas: int
     * }
     */
    public static function auditarCtamovPorVentas(
        int $empresaCodigo,
        array $ventaIds,
        array $codigosCuenta,
        float $tolerancia,
    ): array {
        if ($ventaIds === [] || $empresaCodigo <= 0) {
            return [
                'total_ctamov' => 0.0,
                'asientos_consultados' => 0,
                'sin_ctamov' => 0,
                'dif_lineas' => 0,
                'ok_lineas' => 0,
            ];
        }

        $asientos = DB::table('asiento as a')
            ->join('venta as v', 'v.id', '=', 'a.venta_id')
            ->whereIn('a.venta_id', $ventaIds)
            ->select('a.id', 'a.venta_id', 'a.numeroasiento')
            ->get();

        $cacheCtamov = [];
        $totalCtamov = 0.0;
        $sinCtamov = 0;
        $difLineas = 0;
        $okLineas = 0;
        $consultados = 0;

        foreach ($asientos as $asiento) {
            $numero = trim((string) ($asiento->numeroasiento ?? ''));
            if ($numero === '') {
                $sinCtamov++;
                continue;
            }

            if (! isset($cacheCtamov[$numero])) {
                $cacheCtamov[$numero] = RecepcionProveedorAsientoAuditoriaSupport::lineasCtamovPorNumeroAsiento(
                    $empresaCodigo,
                    $numero,
                );
                $consultados++;
            }

            $filasCtamov = $cacheCtamov[$numero];
            if ($filasCtamov === []) {
                $sinCtamov++;
                continue;
            }

            $totalCtamov += self::sumarVentasDesdeCtamov($filasCtamov, $codigosCuenta);

            $movimientos = Asiento_Movimiento::query()
                ->where('asiento_id', (int) $asiento->id)
                ->with(['cuentacontables', 'centrocostos', 'monedas'])
                ->get();

            $erpLineas = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasErp($movimientos);
            $anitaLineas = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasAnita($filasCtamov);
            $difs = RecepcionProveedorAsientoAuditoriaSupport::diferenciasLineas($erpLineas, $anitaLineas, $tolerancia);

            if ($difs === []) {
                $okLineas++;
            } else {
                $difLineas++;
            }
        }

        return [
            'total_ctamov' => round($totalCtamov, 2),
            'asientos_consultados' => $consultados,
            'sin_ctamov' => $sinCtamov,
            'dif_lineas' => $difLineas,
            'ok_lineas' => $okLineas,
        ];
    }

    /**
     * @param  list<int>  $puntoventaId
     * @return list<int>
     */
    public static function ventaIdsPorPuntoventaJornada(int $puntoventaId, int $empresaId, string $fechaJornada): array
    {
        if ($puntoventaId <= 0) {
            return [];
        }

        return DB::table('venta_gastronomia_emision as e')
            ->join('venta as v', 'v.id', '=', 'e.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->where('v.puntoventa_id', $puntoventaId)
            ->where('pv.empresa_id', $empresaId)
            ->where(function ($q) use ($fechaJornada) {
                $q->whereDate('v.fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('v.fechajornada')
                            ->whereDate('v.fecha', $fechaJornada);
                    });
            })
            ->pluck('v.id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<object{id: int, numeroasiento: string, observacion: string}>
     */
    public static function asientosCierreJornada(int $empresaId, string $fechaJornada): array
    {
        $mapaGrabados = CierreJornadaProcesoAsientosGrabacionSupport::mapaAsientosGrabadosPorEmpresaJornada(
            $empresaId,
            $fechaJornada,
        );
        $idsSnapshot = array_keys($mapaGrabados);

        $query = DB::table('asiento')
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fechaJornada);

        if ($idsSnapshot !== []) {
            $query->whereIn('id', $idsSnapshot);
        } else {
            $query->where(function ($q) use ($fechaJornada) {
                $q->where('observacion', 'like', 'Cierre Waitry jornada '.$fechaJornada.'%')
                    ->orWhere(
                        'observacion',
                        CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
                    );
            });
        }

        return $query
            ->orderBy('id')
            ->get(['id', 'numeroasiento', 'observacion'])
            ->all();
    }

    /**
     * @param  list<object{id: int, numeroasiento: string, observacion: string}>  $asientos
     * @return array{
     *   total_erp: float,
     *   total_ctamov: float,
     *   ok: int,
     *   dif: int,
     *   sin_ctamov: int,
     *   detalle: list<array<string, mixed>>
     * }
     */
    public static function auditarAsientosCierreVsCtamov(
        array $asientos,
        int $empresaCodigo,
        array $codigosCuenta,
        float $tolerancia,
    ): array {
        $totalErp = 0.0;
        $totalCtamov = 0.0;
        $ok = 0;
        $dif = 0;
        $sinCtamov = 0;
        $detalle = [];

        foreach ($asientos as $asiento) {
            $asientoId = (int) ($asiento->id ?? 0);
            $numero = trim((string) ($asiento->numeroasiento ?? ''));
            if ($asientoId <= 0 || $numero === '') {
                continue;
            }

            $movimientosQuery = DB::table('asiento_movimiento as am')
                ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
                ->where('am.asiento_id', $asientoId)
                ->select('am.*', 'cc.codigo')
                ->get();

            $erpVentas = 0.0;
            $set = array_flip($codigosCuenta);
            foreach ($movimientosQuery as $mov) {
                $codigo = (int) ($mov->codigo ?? 0);
                if ($codigo <= 0 || ! isset($set[$codigo])) {
                    continue;
                }
                $erpVentas += -(float) ($mov->monto ?? 0);
            }
            $erpVentas = round($erpVentas, 2);
            $totalErp += $erpVentas;

            $filasCtamov = RecepcionProveedorAsientoAuditoriaSupport::lineasCtamovPorNumeroAsiento(
                $empresaCodigo,
                $numero,
            );

            if ($filasCtamov === []) {
                $sinCtamov++;
                $detalle[] = [
                    'numeroasiento' => $numero,
                    'observacion' => (string) ($asiento->observacion ?? ''),
                    'estado' => 'SIN_CTAMOV',
                    'erp_ventas' => $erpVentas,
                    'ctamov_ventas' => null,
                ];
                continue;
            }

            $ctamovVentas = self::sumarVentasDesdeCtamov($filasCtamov, $codigosCuenta);
            $totalCtamov += $ctamovVentas;

            $movimientos = Asiento_Movimiento::query()
                ->where('asiento_id', $asientoId)
                ->with(['cuentacontables', 'centrocostos', 'monedas'])
                ->get();

            $erpLineas = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasErp($movimientos);
            $anitaLineas = RecepcionProveedorAsientoAuditoriaSupport::normalizarLineasAnita($filasCtamov);
            $difs = RecepcionProveedorAsientoAuditoriaSupport::diferenciasLineas($erpLineas, $anitaLineas, $tolerancia);
            $estado = $difs === [] ? 'OK' : 'DIF';

            if ($estado === 'OK') {
                $ok++;
            } else {
                $dif++;
            }

            $detalle[] = [
                'numeroasiento' => $numero,
                'observacion' => (string) ($asiento->observacion ?? ''),
                'estado' => $estado,
                'erp_ventas' => $erpVentas,
                'ctamov_ventas' => $ctamovVentas,
                'diferencias' => $difs,
            ];
        }

        return [
            'total_erp' => round($totalErp, 2),
            'total_ctamov' => round($totalCtamov, 2),
            'ok' => $ok,
            'dif' => $dif,
            'sin_ctamov' => $sinCtamov,
            'detalle' => $detalle,
        ];
    }

    public static function contabilidadPorFacturaHabilitada(): bool
    {
        return filter_var(config('gastronomia.genera_contabilidad_al_facturar', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  list<array<string, mixed>>  $filasCtamov
     * @param  list<int>  $codigosCuenta
     */
    public static function sumarVentasDesdeCtamov(array $filasCtamov, array $codigosCuenta): float
    {
        if ($filasCtamov === [] || $codigosCuenta === []) {
            return 0.0;
        }

        $set = array_flip($codigosCuenta);
        $total = 0.0;

        foreach ($filasCtamov as $fila) {
            $cuenta = (int) trim((string) ($fila['ctav_cuenta'] ?? '0'));
            if ($cuenta <= 0 || ! isset($set[$cuenta])) {
                continue;
            }

            $importe = (float) ($fila['ctav_importe'] ?? 0);
            $dh = strtoupper(trim((string) ($fila['ctav_d_h'] ?? 'D')));
            // Convención alineada a asiento_movimiento.monto: haber ctamov = crédito ventas (+).
            $total += $dh === 'H' ? $importe : -$importe;
        }

        return round($total, 2);
    }
}
