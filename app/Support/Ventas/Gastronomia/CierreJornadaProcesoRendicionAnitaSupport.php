<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Caja\Cuentacaja;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Rendición rendgastro / rendvalor del proceso de cierre Waitry (PV CAEA del batch CF).
 */
final class CierreJornadaProcesoRendicionAnitaSupport
{
    public const TURNO_LETRA = 'N';

    public const HOST = 'CIERRE-WAITRY';

    /**
     * @param  list<int>  $ventaIds
     * @return list<array{cuentacaja_id:int,monto:float,cotizacion:float}>
     */
    public static function movimientosDesdeVentasProceso(array $ventaIds): array
    {
        if ($ventaIds === []) {
            return [];
        }

        $ventas = Venta::query()
            ->with(['cobranzasDirectas', 'caja_movimientos.cobranzas'])
            ->whereIn('id', $ventaIds)
            ->get();

        /** @var array<int, float> $porCuenta */
        $porCuenta = [];

        foreach ($ventas as $venta) {
            $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
            $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
            foreach ($medios as $lineas) {
                foreach ($lineas as $medio) {
                    $cuentaId = (int) ($medio->cuentacaja_id ?? 0);
                    $monto = round((float) ($medio->monto ?? 0), 2);
                    if ($cuentaId <= 0 || abs($monto) <= 0.0001) {
                        continue;
                    }
                    $porCuenta[$cuentaId] = round(($porCuenta[$cuentaId] ?? 0.) + $monto, 2);
                }
            }
        }

        $movimientos = [];
        foreach ($porCuenta as $cuentaId => $monto) {
            $movimientos[] = [
                'cuentacaja_id' => (int) $cuentaId,
                'monto' => (float) $monto,
                'cotizacion' => 1.0,
            ];
        }

        return $movimientos;
    }

    /**
     * @param  list<int>  $ventaIds
     */
    public static function totalFacturasProceso(array $ventaIds): float
    {
        if ($ventaIds === []) {
            return 0.0;
        }

        $total = (float) Venta::query()
            ->whereIn('id', $ventaIds)
            ->sum('total');

        return round($total, 2);
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<string, mixed>
     */
    public static function armarContextoAnita(
        JornadaGastronomia $jornada,
        int $puntoventaCaeaId,
        int $nroOper,
        array $ventaIds,
        int $cajaId = 0,
        ?int $usuarioId = null,
    ): array {
        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
            ?? $jornada->cierre_en?->format('Y-m-d')
            ?? now()->format('Y-m-d');
        $fechaJornadaCarbon = Carbon::parse($fechaJornada);
        $fechaRend = now();

        $pvCaea = Puntoventa::query()->find($puntoventaCaeaId);
        if ($pvCaea === null) {
            throw new \InvalidArgumentException('Punto de venta CAEA #'.$puntoventaCaeaId.' inexistente.');
        }

        // X / tot_fc_caea: solo el batch CF del proceso. Z se recalcula post-insert por PV (caja).
        $totalFacturadoProceso = self::totalFacturasProceso($ventaIds);
        $totNcProceso = self::totalNotasCreditoProceso($ventaIds);
        $sucursalPv = self::codigoPuntoventaEntero($pvCaea->codigo);

        $filasMovimiento = self::movimientosDesdeVentasProceso($ventaIds);
        $movimientos = self::movimientosComoStubs($filasMovimiento);

        return [
            'nro_oper' => $nroOper,
            'tipo_oper' => substr((string) config('rendicion_gastronomia_anita.tipo_oper', 'F'), 0, 1),
            'empresa_id' => $empresaId,
            'caja_id' => $cajaId,
            'usuario_id' => $usuarioId ?? (int) (Auth::id() ?? 0),
            'fecha_rendicion' => $fechaRend,
            'fecha_jornada' => $fechaJornada,
            'fecha_entera' => (int) $fechaJornadaCarbon->format('Ymd'),
            'fecha_alfa' => $fechaJornadaCarbon->format('Ymd'),
            'hora' => $fechaRend->format('H:i:s'),
            'hora_carga' => now()->format('H:i:s'),
            'fecha_carga' => (int) now()->format('Ymd'),
            'total_x' => $totalFacturadoProceso,
            'total_z' => 0.0,
            'invitacion' => 0.0,
            'tot_nc' => 0.0,
            'tot_redondeo' => 0.0,
            'dif_caja' => 0.0,
            'ultimo_ticket' => self::ultimoTicketVentasProceso($ventaIds),
            'nro_z' => 0,
            'turno_letra' => self::TURNO_LETRA,
            'sucursal_cae' => $sucursalPv,
            'suc_caea' => $sucursalPv,
            'nro_rend_vta' => (int) $jornada->id,
            'host' => self::HOST,
            'tot_fc_caea' => $totalFacturadoProceso,
            'tot_nc_caea' => $totNcProceso,
            'movimientos' => $movimientos,
            'puntoventa_caea_id' => $puntoventaCaeaId,
            'puntoventa_caea_codigo' => (string) $pvCaea->codigo,
            'movimientos_filas' => $filasMovimiento,
        ];
    }

    /**
     * @param  list<int>  $ventaIds
     */
    public static function totalCobradoProceso(array $ventaIds): float
    {
        $total = 0.0;
        foreach (self::movimientosDesdeVentasProceso($ventaIds) as $mov) {
            $total = round($total + (float) ($mov['monto'] ?? 0), 2);
        }

        return round($total, 2);
    }

    /**
     * @param  list<int>  $ventaIds
     */
    private static function totalNotasCreditoProceso(array $ventaIds): float
    {
        if ($ventaIds === []) {
            return 0.0;
        }

        $total = (float) Venta::query()
            ->whereIn('id', $ventaIds)
            ->whereHas('gastronomiaEmision', static function ($emision) {
                $emision->whereNotNull('venta_factura_origen_id');
            })
            ->sum('total');

        return round(abs($total), 2);
    }

    /**
     * @param  list<int>  $ventaIds
     */
    private static function ultimoTicketVentasProceso(array $ventaIds): int
    {
        if ($ventaIds === []) {
            return 0;
        }

        return max(0, (int) Venta::query()->whereIn('id', $ventaIds)->max('numerocomprobante'));
    }

    /**
     * @param  list<array{cuentacaja_id:int,monto:float,cotizacion:float}>  $filas
     * @return list<object{cuentacaja_id:int,monto:float,cotizacion:float,cuentacaja:?Cuentacaja}>
     */
    public static function movimientosComoStubs(array $filas): array
    {
        $cuentaIds = array_values(array_unique(array_filter(array_map(
            static fn (array $f) => (int) ($f['cuentacaja_id'] ?? 0),
            $filas,
        ))));
        $cuentas = $cuentaIds !== []
            ? Cuentacaja::query()->whereIn('id', $cuentaIds)->get()->keyBy('id')
            : collect();

        $out = [];
        foreach ($filas as $fila) {
            $cuentaId = (int) ($fila['cuentacaja_id'] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $out[] = (object) [
                'cuentacaja_id' => $cuentaId,
                'monto' => round((float) ($fila['monto'] ?? 0), 2),
                'cotizacion' => round((float) ($fila['cotizacion'] ?? 1), 4),
                'cuentacaja' => $cuentas->get($cuentaId),
            ];
        }

        return $out;
    }

    private static function codigoPuntoventaEntero(?string $codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 0;
        }

        return (int) preg_replace('/\D+/', '', $codigo);
    }
}
