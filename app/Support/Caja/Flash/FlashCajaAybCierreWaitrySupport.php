<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaFacturaProcesoEmisionService;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAutomaticoSupport;
use Carbon\Carbon;

/**
 * Cierre Waitry vs AyB del flash: el aviso de “faltante” mira si el proceso terminó
 * (factura/omitida/solo_ajuste + asientos + rendgastro), no solo si hay CAEA.
 * El CAEA sigue usándose para detectar AyB corto y para el recálculo.
 */
final class FlashCajaAybCierreWaitrySupport
{
    public const NIVEL_FALTANTE = 'faltante';

    public const NIVEL_INCLUIDO = 'incluido';

    public const NIVEL_CORTO = 'corto';

    public const TOLERANCIA = 0.02;

    public static function procesoCompletado(int $empresaId, string $fecha): bool
    {
        if ($empresaId <= 0 || trim($fecha) === '') {
            return false;
        }

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', Carbon::parse($fecha)->toDateString())
            ->first();

        if ($jornada === null) {
            return false;
        }

        return CierreJornadaProcesoAutomaticoSupport::procesoCompletado($jornada);
    }

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
     *   proceso_completado: bool,
     *   ayb_referencia: float,
     *   ayb_erp: float
     * }
     */
    public static function armarAviso(bool $procesoCompletado, array $caea, float $aybReferencia, float $aybErp): array
    {
        $tiene = (bool) ($caea['tiene'] ?? false);
        $monto = round((float) ($caea['monto'] ?? 0), 2);
        $cantidad = (int) ($caea['cantidad'] ?? 0);
        $aybReferencia = round($aybReferencia, 2);
        $aybErp = round($aybErp, 2);
        $nivel = self::clasificar($procesoCompletado, $tiene, $aybReferencia, $aybErp);

        return [
            'nivel' => $nivel,
            'mensaje' => self::mensaje($nivel, $monto, $cantidad, $tiene),
            'tiene_caea' => $tiene,
            'monto_caea' => $monto,
            'cantidad' => $cantidad,
            'proceso_completado' => $procesoCompletado,
            'ayb_referencia' => $aybReferencia,
            'ayb_erp' => $aybErp,
        ];
    }

    public static function clasificar(
        bool $procesoCompletado,
        bool $tieneCaea,
        float $aybReferencia,
        float $aybErp,
    ): string {
        if (! $procesoCompletado) {
            return self::NIVEL_FALTANTE;
        }

        if ($tieneCaea && ($aybReferencia + self::TOLERANCIA) < $aybErp) {
            return self::NIVEL_CORTO;
        }

        return self::NIVEL_INCLUIDO;
    }

    public static function mensaje(
        string $nivel,
        float $montoCaea,
        int $cantidad,
        bool $tieneCaea = false,
    ): string {
        $montoTxt = number_format($montoCaea, 2, ',', '.');

        return match ($nivel) {
            self::NIVEL_FALTANTE => 'Todavía no terminó el cierre Waitry de esta jornada. '
                .'Si grabás ahora, el AyB puede quedar corto. El cierre automático (~09:00) o un recálculo posterior lo completa.',
            self::NIVEL_CORTO => 'El AyB no incluye el CAEA de cierre Waitry'
                .($cantidad > 0 ? ' ('.$cantidad.' factura'.($cantidad === 1 ? '' : 's').', $ '.$montoTxt.')' : '')
                .'. Recalculá AyB antes de grabar.',
            default => $tieneCaea && $montoCaea > self::TOLERANCIA
                ? 'El cierre Waitry de esta jornada ya terminó. AyB incluye el CAEA ($ '.$montoTxt.').'
                : 'El cierre Waitry de esta jornada ya terminó.',
        };
    }
}
