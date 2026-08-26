<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaFacturaProcesoEmisionService;
use Carbon\Carbon;

/**
 * CAEA de cierre Waitry (PV proceso / CIERRE-JORNADA-WAITRY) vs AyB del flash.
 * Punto único de detección y textos de aviso. El recálculo vive en FlashCajaAybRecalculoService.
 */
final class FlashCajaAybCierreWaitrySupport
{
    public const NIVEL_FALTANTE = 'faltante';

    public const NIVEL_INCLUIDO = 'incluido';

    public const NIVEL_CORTO = 'corto';

    public const TOLERANCIA = 0.02;

    /**
     * @return array{tiene: bool, monto: float, cantidad: int, venta_ids: list<int>}
     */
    public static function resumenCaea(int $empresaId, string $fecha): array
    {
        $vacio = [
            'tiene' => false,
            'monto' => 0.0,
            'cantidad' => 0,
            'venta_ids' => [],
        ];
        if ($empresaId <= 0 || trim($fecha) === '') {
            return $vacio;
        }

        $fechaSql = Carbon::parse($fecha)->toDateString();
        $pcProceso = GastronomiaCierreJornadaFacturaProcesoEmisionService::IDENTIFICADOR_PC_PROCESO;

        $emisiones = VentaGastronomiaEmision::query()
            ->where(function ($q) use ($pcProceso) {
                $q->where('identificador_pc', $pcProceso)
                    ->orWhere('cierre_jornada_proceso_lote', '>', 0);
            })
            ->whereHas('venta', function ($v) use ($empresaId, $fechaSql) {
                $v->whereDate('fechajornada', $fechaSql)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->with('venta:id,total')
            ->get();

        $monto = 0.0;
        $ids = [];
        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if ($venta === null) {
                continue;
            }
            $ids[] = (int) $venta->id;
            $importe = round((float) ($venta->total ?? 0), 2);
            $monto += ($em->venta_factura_origen_id ?? null) !== null
                ? -abs($importe)
                : $importe;
        }

        $monto = round($monto, 2);

        return [
            'tiene' => $ids !== [] && $monto > self::TOLERANCIA,
            'monto' => $monto,
            'cantidad' => count($ids),
            'venta_ids' => $ids,
        ];
    }

    /**
     * @param  array{tiene: bool, monto: float, cantidad: int, venta_ids?: list<int>}  $caea
     * @return array{
     *   nivel: string,
     *   mensaje: string,
     *   tiene_caea: bool,
     *   monto_caea: float,
     *   cantidad: int,
     *   ayb_referencia: float,
     *   ayb_erp: float
     * }
     */
    public static function armarAviso(array $caea, float $aybReferencia, float $aybErp): array
    {
        $tiene = (bool) ($caea['tiene'] ?? false);
        $monto = round((float) ($caea['monto'] ?? 0), 2);
        $cantidad = (int) ($caea['cantidad'] ?? 0);
        $aybReferencia = round($aybReferencia, 2);
        $aybErp = round($aybErp, 2);
        $nivel = self::clasificar($tiene, $aybReferencia, $aybErp);

        return [
            'nivel' => $nivel,
            'mensaje' => self::mensaje($nivel, $monto, $cantidad),
            'tiene_caea' => $tiene,
            'monto_caea' => $monto,
            'cantidad' => $cantidad,
            'ayb_referencia' => $aybReferencia,
            'ayb_erp' => $aybErp,
        ];
    }

    public static function clasificar(bool $tieneCaea, float $aybReferencia, float $aybErp): string
    {
        if (! $tieneCaea) {
            return self::NIVEL_FALTANTE;
        }

        if (($aybReferencia + self::TOLERANCIA) < $aybErp) {
            return self::NIVEL_CORTO;
        }

        return self::NIVEL_INCLUIDO;
    }

    public static function mensaje(string $nivel, float $montoCaea, int $cantidad): string
    {
        $montoTxt = number_format($montoCaea, 2, ',', '.');

        return match ($nivel) {
            self::NIVEL_FALTANTE => 'Todavía no está el CAEA de cierre Waitry de esta jornada. '
                .'Si grabás ahora, el AyB puede quedar corto. El cierre automático (~09:00) o un recálculo posterior lo completa.',
            self::NIVEL_CORTO => 'El AyB no incluye el CAEA de cierre Waitry'
                .($cantidad > 0 ? ' ('.$cantidad.' factura'.($cantidad === 1 ? '' : 's').', $ '.$montoTxt.')' : '')
                .'. Recalculá AyB antes de grabar.',
            default => 'AyB incluye el CAEA de cierre Waitry'
                .($montoCaea > self::TOLERANCIA ? ' ($ '.$montoTxt.')' : '')
                .'.',
        };
    }
}
