<?php

namespace App\Support\Caja\RendicionMaquina;

use App\Models\Caja\RendicionMaquina;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resuelve fondo inicial / comprobante desde turnos previos (ERP).
 * Traducción limpia de calcula_fondo_inicial + comprobantes del C.
 *
 * vale_rep_fondo queda en 0: el C usaba REMEM_lee_remesa_interna / VALEM_graba_vale;
 * no hay bridge ERP aún. Editable manualmente en el workbench (calc_orquestador).
 */
final class RendicionMaquinaPreviasSupport
{
    /** Preferencia de turno al buscar cierre del día anterior. */
    private const PREFERENCIA_CIERRE = [
        RendicionMaquinaTurno::NOCHE => 1,
        RendicionMaquinaTurno::TARDE => 2,
        RendicionMaquinaTurno::MANIANA => 3,
        RendicionMaquinaTurno::COMPLETO => 4,
    ];

    /**
     * @return array{
     *   fondo_inicial: float,
     *   comprobante: float,
     *   vale_rep_fondo: float,
     *   prev_fondo_cierre: float,
     *   prev_transferencia: float,
     *   impuesto_drop_dia_ant: float,
     *   origen_fondo: string
     * }
     */
    public static function resolver(int $empresaId, string $fechaYmd, string $turno, ?int $exceptoId = null): array
    {
        $turno = RendicionMaquinaTurno::normalizar($turno);
        $out = [
            'fondo_inicial' => 0.0,
            'comprobante' => 0.0,
            'vale_rep_fondo' => 0.0,
            'prev_fondo_cierre' => 0.0,
            'prev_transferencia' => 0.0,
            'impuesto_drop_dia_ant' => 0.0,
            'origen_fondo' => 'ninguno',
        ];

        if ($empresaId <= 0) {
            return $out;
        }

        if (RendicionMaquinaTurno::esCompleto($turno)) {
            $out['impuesto_drop_dia_ant'] = self::impuestoDropDiaAnterior($empresaId, $fechaYmd);
            $out['origen_fondo'] = 'completo_cero';

            return $out;
        }

        $prev = self::buscarRendicionPrevia($empresaId, $fechaYmd, $turno, $exceptoId);
        if ($prev !== null) {
            $out['fondo_inicial'] = round((float) $prev->fondo_cierre, 2);
            $out['prev_fondo_cierre'] = $out['fondo_inicial'];
            $out['prev_transferencia'] = round((float) $prev->transferencia, 2);
            $out['origen_fondo'] = 'rendicion#'.$prev->id.' turno '.$prev->turno;
        }

        if (RendicionMaquinaTurno::esManiana($turno)) {
            $out['comprobante'] = $out['prev_transferencia'];
        } else {
            $out['comprobante'] = self::sumaTransferenciasDelDiaAnteriores(
                $empresaId,
                $fechaYmd,
                $turno,
                $exceptoId
            );
        }

        return $out;
    }

    private static function buscarRendicionPrevia(
        int $empresaId,
        string $fechaYmd,
        string $turno,
        ?int $exceptoId
    ): ?RendicionMaquina {
        $nivel = match ($turno) {
            RendicionMaquinaTurno::TARDE => 1,
            RendicionMaquinaTurno::NOCHE => 2,
            default => 0,
        };

        if ($nivel > 0) {
            $anteriores = $nivel === 1
                ? [RendicionMaquinaTurno::MANIANA]
                : [RendicionMaquinaTurno::TARDE, RendicionMaquinaTurno::MANIANA];

            $hit = self::queryBase($empresaId, $exceptoId)
                ->whereDate('fecha', $fechaYmd)
                ->whereIn('turno', $anteriores)
                ->orderByDesc('id')
                ->get();

            $elegida = self::elegirPorPreferenciaTurno($hit);
            if ($elegida) {
                return $elegida;
            }
        }

        $fechaAnt = Carbon::parse($fechaYmd)->subDay()->format('Y-m-d');
        $anterioresDia = self::queryBase($empresaId, $exceptoId)
            ->whereDate('fecha', $fechaAnt)
            ->whereIn('turno', array_keys(self::PREFERENCIA_CIERRE))
            ->orderByDesc('id')
            ->get();

        return self::elegirPorPreferenciaTurno($anterioresDia);
    }

    /**
     * @param  Collection<int, RendicionMaquina>  $filas
     */
    private static function elegirPorPreferenciaTurno(Collection $filas): ?RendicionMaquina
    {
        if ($filas->isEmpty()) {
            return null;
        }

        return $filas->sortBy(function (RendicionMaquina $r) {
            $pref = self::PREFERENCIA_CIERRE[$r->turno] ?? 99;

            return sprintf('%02d-%010d', $pref, PHP_INT_MAX - (int) $r->id);
        })->first();
    }

    private static function sumaTransferenciasDelDiaAnteriores(
        int $empresaId,
        string $fechaYmd,
        string $turno,
        ?int $exceptoId
    ): float {
        $nivel = match ($turno) {
            RendicionMaquinaTurno::TARDE => [RendicionMaquinaTurno::MANIANA],
            RendicionMaquinaTurno::NOCHE => [RendicionMaquinaTurno::MANIANA, RendicionMaquinaTurno::TARDE],
            default => [],
        };
        if ($nivel === []) {
            return 0.0;
        }

        return round((float) self::queryBase($empresaId, $exceptoId)
            ->whereDate('fecha', $fechaYmd)
            ->whereIn('turno', $nivel)
            ->sum('transferencia'), 2);
    }

    private static function impuestoDropDiaAnterior(int $empresaId, string $fechaYmd): float
    {
        $fechaAnt = Carbon::parse($fechaYmd)->subDay()->format('Y-m-d');
        $completo = self::queryBase($empresaId, null)
            ->whereDate('fecha', $fechaAnt)
            ->where('turno', RendicionMaquinaTurno::COMPLETO)
            ->orderByDesc('id')
            ->first();

        if (! $completo) {
            return 0.0;
        }

        $inputs = is_array($completo->inputs_json) ? $completo->inputs_json : [];

        return round((float) ($inputs['impuesto_drop'] ?? $inputs['inputs.impuesto_drop'] ?? 0), 2);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Caja\RendicionMaquina>
     */
    private static function queryBase(int $empresaId, ?int $exceptoId)
    {
        $q = RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', '!=', RendicionMaquina::ESTADO_ANULADA);

        if ($exceptoId) {
            $q->where('id', '!=', $exceptoId);
        }

        return $q;
    }
}
