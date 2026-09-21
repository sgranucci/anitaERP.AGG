<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cobranza;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Venta;
use App\Services\Caja\CobranzaService;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cobranza del POS Local reutilizando CobranzaService::guardaCobranzaGastronomia (payload genérico).
 */
final class FacturacionLocalCobranzaService
{
    private const MONEDA_PESOS_ID = 1;

    public function __construct(
        private readonly CobranzaService $cobranzaService,
    ) {
    }

    /**
     * NC de mostrador sobre FAC de Facturación Local: espeja la cobranza de la FAC como devolución.
     *
     * Solo actúa si la FAC tiene `facturacion_local_emision`. No toca AGG (gastronomía/estacionamiento/
     * mostrador genérico) ni El Bierzo. El POS Local ya cobra en EmisionService (origen_facturacion_local).
     *
     * @param  array<string, mixed>|null  $opcionesEmision
     * @return array{cobranza_id:int,caja_movimiento_id?:int}|null
     */
    public function revertirCobranzaFacSiMostradorNc(
        int $ventaFacId,
        Venta $ventaNc,
        ?array $opcionesEmision = null,
    ): ?array {
        $op = is_array($opcionesEmision) ? $opcionesEmision : [];
        // POS Local / gastronomía / estacionamiento registran su propia devolución.
        if (
            ! empty($op['origen_facturacion_local'])
            || ! empty($op['origen_estacionamiento'])
            || ! empty($op['emision_pos_arca'])
            || ! empty($op['omitir_movimiento_stock'])
        ) {
            return null;
        }

        if ($ventaFacId <= 0 || (int) $ventaNc->id <= 0) {
            return null;
        }

        $emision = FacturacionLocalEmision::query()
            ->where('venta_id', $ventaFacId)
            ->first();
        if (! $emision) {
            // FAC no es Facturación Local → AGG / El Bierzo / otros circuitos intactos.
            return null;
        }

        if (Cobranza::query()->where('venta_id', (int) $ventaNc->id)->exists()) {
            return null;
        }

        $local = LocalVenta::query()->find((int) $emision->local_venta_id);
        if (! $local) {
            throw new InvalidArgumentException(
                'No se pudo revertir la cobranza: local de Facturación Local inexistente (emisión '.$emision->id.').'
            );
        }

        $mediosFac = $this->mediosDesdeVentaFac($ventaFacId);
        if ($mediosFac === []) {
            // Ticket regalo / FAC Local sin cobranza: no hay nada que revertir.
            return null;
        }

        $totalNc = abs((float) ($ventaNc->total ?? 0));
        if ($totalNc <= 0.009) {
            throw new InvalidArgumentException('No se puede revertir cobranza: total de la NC es 0.');
        }

        $lineas = $this->prorratearMediosAlTotal($mediosFac, $totalNc);
        $resultado = $this->registrar($ventaNc, $local, $lineas, true);

        if ((int) ($emision->venta_nc_id ?? 0) <= 0) {
            $emision->venta_nc_id = (int) $ventaNc->id;
            $emision->save();
        }

        return $resultado;
    }

    /**
     * Medios de cobranza de una FAC Local (para devolución NC admin / mostrador).
     *
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:?string}>
     */
    public function mediosDesdeVentaFac(int $ventaFacId): array
    {
        $cajaMovIds = Caja_Movimiento::query()
            ->where('venta_id', $ventaFacId)
            ->whereNotNull('cobranza_id')
            ->whereNull('caja_movimiento_revertido_por_id')
            ->pluck('id');
        if ($cajaMovIds->isEmpty()) {
            return [];
        }

        $filas = DB::table('caja_movimiento_cuentacaja')
            ->whereIn('caja_movimiento_id', $cajaMovIds)
            ->orderBy('id')
            ->get(['cuentacaja_id', 'moneda_id', 'monto', 'cotizacion', 'observacion']);

        $medios = [];
        foreach ($filas as $fila) {
            $monto = abs((float) ($fila->monto ?? 0));
            if ($monto <= 0.009) {
                continue;
            }
            $medios[] = [
                'cuentacaja_id' => (int) $fila->cuentacaja_id,
                'moneda_id' => (int) ($fila->moneda_id ?: self::MONEDA_PESOS_ID),
                'monto' => $monto,
                'cotizacion' => (float) (($fila->cotizacion ?? 0) > 0 ? $fila->cotizacion : 1.),
                'observacion' => 'Devolución NC Facturación Local',
            ];
        }

        return $medios;
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion?:string|null}>  $medios
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion?:string|null}>
     */
    private function prorratearMediosAlTotal(array $medios, float $totalNc): array
    {
        $suma = 0.;
        foreach ($medios as $m) {
            $suma += (float) $m['monto'] * (float) ($m['cotizacion'] ?? 1.);
        }
        if ($suma <= 0.009) {
            throw new InvalidArgumentException('Medios de cobranza de la FAC sin monto.');
        }

        $factor = min(1.0, $totalNc / $suma);
        $lineas = [];
        $acum = 0.;
        $ultimo = count($medios) - 1;
        foreach ($medios as $i => $m) {
            $cot = (float) ($m['cotizacion'] ?? 1.);
            if ($cot <= 0.) {
                $cot = 1.;
            }
            if ($i === $ultimo) {
                $monto = round(($totalNc - $acum) / $cot, 2);
            } else {
                $monto = round((float) $m['monto'] * $factor, 2);
                $acum += round($monto * $cot, 2);
            }
            if ($monto <= 0.009) {
                continue;
            }
            $lineas[] = [
                'cuentacaja_id' => (int) $m['cuentacaja_id'],
                'moneda_id' => (int) ($m['moneda_id'] ?? self::MONEDA_PESOS_ID),
                'monto' => $monto,
                'cotizacion' => $cot,
                'observacion' => (string) ($m['observacion'] ?? 'Devolución NC Facturación Local'),
            ];
        }

        if ($lineas === []) {
            throw new InvalidArgumentException('No se pudieron armar medios de devolución para la NC.');
        }

        return $lineas;
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id?:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{cobranza_id:int,caja_movimiento_id?:int}
     */
    public function registrar(
        Venta $venta,
        LocalVenta $local,
        array $mediosPago,
        bool $esDevolucion = false,
    ): array {
        if ($mediosPago === []) {
            throw new InvalidArgumentException('Indique al menos un medio de cobro.');
        }

        $tipoCajaId = $esDevolucion ? $local->tipoCajaDevolucionId() : $local->tipoCajaId();
        if ($tipoCajaId <= 0) {
            throw new InvalidArgumentException('Configure el tipo de transacción de caja del local.');
        }

        $empresaId = (int) ($local->empresa_id ?: $venta->empresa_id ?: 0);
        $lineas = [];
        $total = 0.;
        foreach ($mediosPago as $medio) {
            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            $monedaId = (int) ($medio['moneda_id'] ?? self::MONEDA_PESOS_ID);
            $monto = (float) ($medio['monto'] ?? 0);
            if ($cuentacajaId <= 0 || $monto <= 0.) {
                throw new InvalidArgumentException('Cada medio debe tener cuenta de caja y monto > 0.');
            }
            $cotizacion = isset($medio['cotizacion']) && (float) $medio['cotizacion'] > 0
                ? (float) $medio['cotizacion']
                : $this->cotizacion($venta->fecha, $monedaId, $empresaId);
            $cupon = trim((string) ($medio['numerocupon'] ?? ''));
            $observacion = trim((string) ($medio['observacion'] ?? ''));
            if ($cupon !== '') {
                $observacion = trim($observacion.' Cupón '.$cupon);
            }
            $lineas[] = [
                'cuentacaja_id' => $cuentacajaId,
                'moneda_id' => $monedaId,
                'monto' => $monto,
                'cotizacion' => $cotizacion,
                'observacion' => $observacion !== '' ? $observacion : 'Facturación Local',
            ];
            $total += $monto * $cotizacion;
        }

        $detallePrefijo = $esDevolucion ? 'Devolución Facturación Local — ' : 'Cobranza Facturación Local — ';

        return $this->cobranzaService->guardaCobranzaGastronomia([
            'venta' => $venta,
            'empresa_id' => $empresaId > 0 ? $empresaId : (int) $venta->empresa_id,
            'tipotransaccion_caja_id' => $tipoCajaId,
            'totalfinalcobranza' => round($total, 2),
            'monedafinalcobranza_id' => self::MONEDA_PESOS_ID,
            'cotizacion_cobranza' => 1.,
            'lineas' => $lineas,
            'genera_contabilidad' => (bool) config('facturacion_local.genera_contabilidad_cobranza', false),
            'detalle' => $detallePrefijo.$venta->codigo,
        ]);
    }

    private function cotizacion($fecha, int $monedaId, int $empresaId): float
    {
        if ($monedaId <= 1) {
            return 1.;
        }
        $ymd = is_string($fecha) ? $fecha : (string) ($fecha?->format('Y-m-d') ?? date('Y-m-d'));

        return (float) (CotizacionTesoreriaConsultaSupport::ventaPorMonedaId($ymd, $monedaId, $empresaId) ?: 1.);
    }
}
