<?php

namespace App\Support\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Compras\PropuestaPago;
use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Database\SqlDialectSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Workbench unificado SP + IE + Propuesta (grilla operativa).
 */
class TesoreriaWorkbenchSupport
{
    /**
     * @return array{
     *   filas: Collection<int, object>,
     *   contadores: array<string,int>,
     *   total_monto: float
     * }
     */
    public static function grillaOperativa(
        ?int $empresaId = null,
        ?string $tipo = null,
        int $dias = 60,
        int $limit = 120
    ): array {
        $desde = Carbon::today()->subDays(max(7, $dias))->toDateString();
        $tipo = $tipo ? strtoupper($tipo) : null;
        $porFuente = (int) ceil($limit / max(1, count(array_filter([
            $tipo === null || $tipo === 'PP',
            $tipo === null || $tipo === 'SP',
            $tipo === null || $tipo === 'IE',
        ]))));

        $filas = collect();

        if (($tipo === null || $tipo === 'PP') && can('listar-propuesta-pago', false)) {
            $filas = $filas->merge(self::filasPropuesta($empresaId, $desde, $porFuente));
        }
        if (($tipo === null || $tipo === 'SP') && can('listar-solicitud-pago', false)) {
            $filas = $filas->merge(self::filasSolicitud($empresaId, $desde, $porFuente));
        }
        if (($tipo === null || $tipo === 'IE') && can('listar-ingresos-egresos-caja', false)) {
            $filas = $filas->merge(self::filasIngresoEgreso($empresaId, $desde, $porFuente));
        }

        $filas = $filas
            ->sortByDesc(fn ($r) => $r->fecha.'-'.$r->tipo.'-'.$r->id)
            ->values()
            ->take($limit);

        return [
            'filas' => $filas,
            'contadores' => [
                'PP' => $filas->where('tipo', 'PP')->count(),
                'SP' => $filas->where('tipo', 'SP')->count(),
                'IE' => $filas->where('tipo', 'IE')->count(),
                'total' => $filas->count(),
            ],
            'total_monto' => round((float) $filas->sum('monto'), 2),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private static function filasPropuesta(?int $empresaId, string $desde, int $limit): Collection
    {
        $estados = ['BORRADOR', 'EN_APROBACION', 'AUTORIZADA', 'EJECUTADA_PARCIAL'];

        return PropuestaPago::query()
            ->with(['empresas:id,nombre'])
            ->whereIn('estado', $estados)
            ->whereDate('fecha', '>=', $desde)
            ->when($empresaId && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($p) {
                $puede = can('editar-propuesta-pago', false) || can('listar-propuesta-pago', false);

                return (object) [
                    'tipo' => 'PP',
                    'tipo_label' => 'Propuesta',
                    'id' => (int) $p->id,
                    'fecha' => optional($p->fecha)->format('Y-m-d'),
                    'empresa_id' => (int) $p->empresa_id,
                    'empresa' => $p->empresas->nombre ?? '',
                    'estado' => (string) $p->estado,
                    'monto' => (float) ($p->monto_autorizado ?: $p->monto_total),
                    'detalle' => mb_substr((string) $p->detalle, 0, 80),
                    'url' => $puede ? route('editar_propuesta_pago', $p->id) : null,
                    'prioridad' => match ((string) $p->estado) {
                        'AUTORIZADA' => 1,
                        'EN_APROBACION' => 2,
                        'EJECUTADA_PARCIAL' => 3,
                        default => 5,
                    },
                ];
            });
    }

    /**
     * @return Collection<int, object>
     */
    private static function filasSolicitud(?int $empresaId, string $desde, int $limit): Collection
    {
        $estados = ['EMITIDA', 'CONTROLADA', 'AUTORIZADA'];

        return Solicitudpago::query()
            ->with(['empresas:id,nombre'])
            ->whereIn('estado', $estados)
            ->whereDate('fecha', '>=', $desde)
            ->when($empresaId && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($s) {
                $puede = can('editar-solicitud-pago', false) || can('listar-solicitud-pago', false);

                return (object) [
                    'tipo' => 'SP',
                    'tipo_label' => 'Solicitud',
                    'id' => (int) $s->id,
                    'fecha' => optional($s->fecha)->format('Y-m-d'),
                    'empresa_id' => (int) $s->empresa_id,
                    'empresa' => $s->empresas->nombre ?? '',
                    'estado' => (string) $s->estado,
                    'monto' => (float) $s->monto,
                    'detalle' => mb_substr((string) ($s->detalle ?? $s->codigo ?? ''), 0, 80),
                    'url' => $puede ? route('editar_solicitudpago', $s->id) : null,
                    'prioridad' => match ((string) $s->estado) {
                        'AUTORIZADA' => 1,
                        'CONTROLADA' => 2,
                        default => 4,
                    },
                ];
            });
    }

    /**
     * @return Collection<int, object>
     */
    private static function filasIngresoEgreso(?int $empresaId, string $desde, int $limit): Collection
    {
        $montoExpr = SqlDialectSupport::coalesce('SUM(cmc.monto * CASE WHEN cmc.moneda_id > 1 THEN '.SqlDialectSupport::coalesce('cmc.cotizacion', '1').' ELSE 1 END)', '0');

        $rows = Caja_Movimiento::query()
            ->from('caja_movimiento as cm')
            ->leftJoin('empresa as e', 'e.id', '=', 'cm.empresa_id')
            ->leftJoin('caja_movimiento_cuentacaja as cmc', 'cmc.caja_movimiento_id', '=', 'cm.id')
            ->leftJoin('tipotransaccion_caja as ttc', 'ttc.id', '=', 'cm.tipotransaccion_caja_id')
            ->selectRaw('cm.id, cm.fecha, cm.empresa_id, cm.detalle, cm.numerotransaccion, e.nombre as empresa_nombre, ttc.abreviatura as tipo_abrev, '.$montoExpr.' as monto')
            ->whereDate('cm.fecha', '>=', $desde)
            ->when($empresaId && $empresaId > 0, fn ($q) => $q->where('cm.empresa_id', $empresaId))
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('caja_movimiento_estado as cme')
                    ->whereColumn('cme.caja_movimiento_id', 'cm.id')
                    ->whereIn('cme.estado', ['B', 'R'])
                    ->whereRaw('cme.id = (select max(cme2.id) from caja_movimiento_estado cme2 where cme2.caja_movimiento_id = cm.id)');
            })
            ->groupBy('cm.id', 'cm.fecha', 'cm.empresa_id', 'cm.detalle', 'cm.numerotransaccion', 'e.nombre', 'ttc.abreviatura')
            ->orderByDesc('cm.fecha')
            ->orderByDesc('cm.id')
            ->limit($limit)
            ->get();

        $puede = can('editar-ingresos-egresos-caja', false) || can('listar-ingresos-egresos-caja', false);

        return $rows->map(function ($r) use ($puede) {
            return (object) [
                'tipo' => 'IE',
                'tipo_label' => 'Ing/Egr',
                'id' => (int) $r->id,
                'fecha' => $r->fecha ? Carbon::parse($r->fecha)->format('Y-m-d') : '',
                'empresa_id' => (int) $r->empresa_id,
                'empresa' => (string) ($r->empresa_nombre ?? ''),
                'estado' => 'ACTIVO',
                'monto' => (float) $r->monto,
                'detalle' => mb_substr(trim(($r->tipo_abrev ?? '').' '.($r->numerotransaccion ?? '').' '.($r->detalle ?? '')), 0, 80),
                'url' => $puede ? route('editar_ingresoegreso', $r->id) : null,
                'prioridad' => 3,
            ];
        });
    }
}
