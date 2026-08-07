<?php

namespace App\Support\Caja\RendicionMaquina;

use App\Models\Caja\RendicionMaquina;
use App\Support\Caja\Remesa\RemesaInternaErpSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Previas de rendición de máquinas (fondo, comprobante, vale, impuesto, drop ant.).
 *
 * Contrato de lectura (obligatorio para migración Anita → ERP):
 *   1) ERP primero (rendicion_maquina / remesa)
 *   2) Si no hay dato usable → Anita (rendmaquina / rememae)
 *
 * Así, cuando el proceso opere solo en ERP, deja de depender de Anita sin cambiar
 * la pantalla: el fallback Anita simplemente no encuentra filas.
 */
final class RendicionMaquinaPreviasSupport
{
    /**
     * Preferencia cierre día anterior para turno M (Anita pisa M→T→N→C ⇒ C gana).
     *
     * @var array<string, int>
     */
    private const PREFERENCIA_CIERRE_DIA_ANT = [
        RendicionMaquinaTurno::COMPLETO => 1,
        RendicionMaquinaTurno::NOCHE => 2,
        RendicionMaquinaTurno::TARDE => 3,
        RendicionMaquinaTurno::MANIANA => 4,
    ];

    /** @var array<string, int> */
    private const PREFERENCIA_MISMO_DIA = [
        RendicionMaquinaTurno::NOCHE => 1,
        RendicionMaquinaTurno::TARDE => 2,
        RendicionMaquinaTurno::MANIANA => 3,
    ];

    /**
     * @return array{
     *   fondo_inicial: float,
     *   comprobante: float,
     *   vale_rep_fondo: float,
     *   prev_fondo_cierre: float,
     *   prev_transferencia: float,
     *   impuesto_drop: float,
     *   impuesto_drop_dia_ant: float,
     *   drop_bill_ant_completo: float,
     *   drop_rul_ant_completo: float,
     *   origen_fondo: string,
     *   origen_comprobante: string,
     *   origen_vale_rep_fondo: string,
     *   origen_impuesto_drop: string,
     *   origen_drop_ant_completo: string
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
            'impuesto_drop' => 0.0,
            'impuesto_drop_dia_ant' => 0.0,
            'drop_bill_ant_completo' => 0.0,
            'drop_rul_ant_completo' => 0.0,
            'origen_fondo' => 'ninguno',
            'origen_comprobante' => 'ninguno',
            'origen_vale_rep_fondo' => 'ninguno',
            'origen_impuesto_drop' => 'ninguno',
            'origen_drop_ant_completo' => 'ninguno',
        ];

        if ($empresaId <= 0) {
            return $out;
        }

        // Impuesto del C anterior: carga en inputs solo en M (Anita T/N van en 0).
        // impuesto_drop_dia_ant queda disponible para dif. caja del C.
        self::aplicarImpuestoDrop($out, $empresaId, $fechaYmd, $turno, $exceptoId);
        self::aplicarDropAnteriorCompleto($out, $empresaId, $fechaYmd, $exceptoId);

        // Completo: misma semilla de apertura que la mañana (fondo + comprobante del día).
        // Vale rep. fondo no aplica en C (calcula_rendicion_turno_completo lo deja en 0).
        $turnoFondo = RendicionMaquinaTurno::esCompleto($turno)
            ? RendicionMaquinaTurno::MANIANA
            : $turno;

        self::aplicarFondoInicial($out, $empresaId, $fechaYmd, $turnoFondo, $exceptoId);
        self::aplicarComprobante($out, $empresaId, $fechaYmd, $turnoFondo, $exceptoId);

        if (RendicionMaquinaTurno::esCompleto($turno)) {
            $out['origen_fondo'] = 'completo_como_m:'.$out['origen_fondo'];
            $out['origen_comprobante'] = 'completo_como_m:'.$out['origen_comprobante'];

            return $out;
        }

        if (RendicionMaquinaTurno::esManiana($turno)) {
            self::aplicarValeRepFondo($out, $empresaId, $fechaYmd);
        }

        return $out;
    }

    /**
     * Fondo inicial: ERP → Anita.
     *
     * Mañana / cierre día ant.: fondo_cierre − transferencia de la previa.
     * Tarde / noche: fondo_cierre de la previa − suma de transferencias de turnos
     * anteriores del mismo día (comprobante). Así la noche resta M+T y no solo T
     * (el fondo_cierre de T vuelve a 1.100M y si se resta solo T se pierde la transf. de M).
     *
     * @param  array<string, mixed>  $out
     */
    private static function aplicarFondoInicial(
        array &$out,
        int $empresaId,
        string $fechaYmd,
        string $turno,
        ?int $exceptoId
    ): void {
        $prev = self::buscarRendicionPrevia($empresaId, $fechaYmd, $turno, $exceptoId);
        if ($prev !== null) {
            $fondoCierre = round((float) $prev->fondo_cierre, 2);
            $transferencia = self::transferenciaARestarFondoInicial(
                $empresaId,
                $fechaYmd,
                $turno,
                $exceptoId,
                round((float) $prev->transferencia, 2)
            );
            $out['fondo_inicial'] = round($fondoCierre - $transferencia, 2);
            $out['prev_fondo_cierre'] = $fondoCierre;
            $out['prev_transferencia'] = $transferencia;
            $out['origen_fondo'] = 'erp#'.$prev->id.' turno '.$prev->turno;

            return;
        }

        $anita = RendicionMaquinaAnitaPreviasSupport::fondoInicial($empresaId, $fechaYmd, $turno);
        if ($anita === null || abs((float) $anita['fondo_inicial']) < 0.00001) {
            return;
        }

        $out['fondo_inicial'] = round((float) $anita['fondo_inicial'], 2);
        $out['prev_transferencia'] = round((float) ($anita['transferencia'] ?? 0), 2);
        $out['origen_fondo'] = $anita['origen'];
    }

    /**
     * Monto a restar del fondo_cierre de la previa para obtener fondo_inicial.
     */
    private static function transferenciaARestarFondoInicial(
        int $empresaId,
        string $fechaYmd,
        string $turno,
        ?int $exceptoId,
        float $transferenciaPrevia
    ): float {
        if (in_array($turno, [RendicionMaquinaTurno::TARDE, RendicionMaquinaTurno::NOCHE], true)) {
            $suma = self::sumaTransferenciasDelDiaAnteriores($empresaId, $fechaYmd, $turno, $exceptoId);
            if (abs($suma) > 0.00001) {
                return $suma;
            }
        }

        return $transferenciaPrevia;
    }

    /**
     * Comprobante: ERP (sumas de transferencias) → Anita.
     * M: M+T+N día anterior. T/N: turnos previos del mismo día.
     *
     * @param  array<string, mixed>  $out
     */
    private static function aplicarComprobante(
        array &$out,
        int $empresaId,
        string $fechaYmd,
        string $turno,
        ?int $exceptoId
    ): void {
        if (RendicionMaquinaTurno::esManiana($turno)) {
            $erp = self::sumaTransferenciasDiaAnteriorParciales($empresaId, $fechaYmd, $exceptoId);
            if (abs($erp) > 0.00001) {
                $out['comprobante'] = $erp;
                $out['prev_transferencia'] = $erp;
                $out['origen_comprobante'] = 'erp_transfer_mtn';

                return;
            }

            $anita = RendicionMaquinaAnitaPreviasSupport::comprobanteManiana($empresaId, $fechaYmd);
            if ($anita !== null && abs((float) $anita['comprobante']) > 0.00001) {
                $out['comprobante'] = round((float) $anita['comprobante'], 2);
                $out['prev_transferencia'] = $out['comprobante'];
                $out['origen_comprobante'] = $anita['origen'];
            }

            return;
        }

        $erp = self::sumaTransferenciasDelDiaAnteriores($empresaId, $fechaYmd, $turno, $exceptoId);
        if (abs($erp) > 0.00001) {
            $out['comprobante'] = $erp;
            $out['origen_comprobante'] = 'erp_comp_'.$turno;

            return;
        }

        $anita = RendicionMaquinaAnitaPreviasSupport::comprobanteParcialMismoDia($empresaId, $fechaYmd, $turno);
        if ($anita !== null && abs((float) $anita['comprobante']) > 0.00001) {
            $out['comprobante'] = round((float) $anita['comprobante'], 2);
            $out['origen_comprobante'] = $anita['origen'];
        }
    }

    /**
     * Vale rep. fondo (solo M): remesa ERP → rememae Anita.
     *
     * @param  array<string, mixed>  $out
     */
    private static function aplicarValeRepFondo(array &$out, int $empresaId, string $fechaYmd): void
    {
        $remesa = RemesaInternaErpSupport::leeRemesaInterna($empresaId, $fechaYmd);
        if (abs((float) ($remesa['vale_rep_fondo'] ?? 0)) > 0.00001) {
            $out['vale_rep_fondo'] = round((float) $remesa['vale_rep_fondo'], 2);
            $out['origen_vale_rep_fondo'] = $remesa['origen'];

            return;
        }

        $anita = RendicionMaquinaRemesaAnitaSupport::leeRemesaInterna($empresaId, $fechaYmd);
        $out['vale_rep_fondo'] = round((float) ($anita['vale_rep_fondo'] ?? 0), 2);
        $out['origen_vale_rep_fondo'] = $anita['origen'];
    }

    /**
     * Impuesto drop del C del día anterior.
     * - `impuesto_drop_dia_ant`: siempre (dif. caja del C).
     * - `impuesto_drop` (input / neto rodillo): solo turno M; T/N quedan en 0 como Anita.
     *
     * @param  array<string, mixed>  $out
     */
    private static function aplicarImpuestoDrop(
        array &$out,
        int $empresaId,
        string $fechaYmd,
        string $turno,
        ?int $exceptoId
    ): void {
        $fechaAnt = Carbon::parse($fechaYmd)->subDay()->format('Y-m-d');
        $completo = self::queryBase($empresaId, $exceptoId)
            ->whereDate('fecha', $fechaAnt)
            ->where('turno', RendicionMaquinaTurno::COMPLETO)
            ->orderByDesc('id')
            ->first();

        $imp = 0.0;
        $origen = 'ninguno';

        if ($completo) {
            $inputs = is_array($completo->inputs_json) ? $completo->inputs_json : [];
            $imp = round((float) ($inputs['impuesto_drop'] ?? $inputs['inputs.impuesto_drop'] ?? 0), 2);
            $origen = 'erp#'.$completo->id;
        } else {
            $anita = RendicionMaquinaAnitaPreviasSupport::impuestoDropDiaAnterior($empresaId, $fechaYmd);
            if ($anita === null) {
                return;
            }
            $imp = round((float) $anita['impuesto_drop'], 2);
            $origen = $anita['origen'];
        }

        $out['impuesto_drop_dia_ant'] = $imp;
        $out['origen_impuesto_drop'] = $origen;
        // Paridad Anita: solo mañana reutiliza imp_drop del C anterior en la carga.
        if (RendicionMaquinaTurno::esManiana($turno)) {
            $out['impuesto_drop'] = $imp;
        }
    }

    /**
     * Drop anterior del C: neto del M ERP del mismo día → M Anita.
     *
     * @param  array<string, mixed>  $out
     */
    private static function aplicarDropAnteriorCompleto(
        array &$out,
        int $empresaId,
        string $fechaYmd,
        ?int $exceptoId
    ): void {
        $maniana = self::queryBase($empresaId, $exceptoId)
            ->whereDate('fecha', $fechaYmd)
            ->where('turno', RendicionMaquinaTurno::MANIANA)
            ->orderByDesc('id')
            ->first();

        if ($maniana) {
            $calc = is_array($maniana->calc_json) ? $maniana->calc_json : [];
            $vars = is_array($calc['variables'] ?? null) ? $calc['variables'] : [];
            $inputs = is_array($maniana->inputs_json) ? $maniana->inputs_json : [];
            // Preferir neto calculado; si no, drop_billete (ya neto en pantallas nuevas)
            // o bruto − impuesto en grabaciones viejas.
            if (isset($vars['calc.drop_bill_rodillo'])) {
                $dr = (float) $vars['calc.drop_bill_rodillo'];
            } elseif (isset($inputs['drop_billete_bruto'])) {
                $dr = (float) ($inputs['drop_billete'] ?? 0);
            } else {
                $dr = (float) ($inputs['drop_billete'] ?? 0)
                    - (float) ($inputs['impuesto_drop'] ?? 0);
            }
            $rul = (float) ($inputs['drop_ruleta'] ?? $inputs['inputs.drop_ruleta'] ?? 0);
            $out['drop_bill_ant_completo'] = round($dr, 2);
            $out['drop_rul_ant_completo'] = round($rul, 2);
            $out['origen_drop_ant_completo'] = 'erp#'.$maniana->id;

            return;
        }

        $anita = RendicionMaquinaAnitaPreviasSupport::dropAnteriorDesdeManiana($empresaId, $fechaYmd);
        if ($anita === null) {
            return;
        }

        $out['drop_bill_ant_completo'] = round((float) $anita['drop_bill_ant'], 2);
        $out['drop_rul_ant_completo'] = round((float) $anita['drop_rul_ant'], 2);
        $out['origen_drop_ant_completo'] = $anita['origen'];
    }

    private static function buscarRendicionPrevia(
        int $empresaId,
        string $fechaYmd,
        string $turno,
        ?int $exceptoId
    ): ?RendicionMaquina {
        if ($turno === RendicionMaquinaTurno::TARDE) {
            $hit = self::queryBase($empresaId, $exceptoId)
                ->whereDate('fecha', $fechaYmd)
                ->where('turno', RendicionMaquinaTurno::MANIANA)
                ->orderByDesc('id')
                ->get();

            return self::elegirPorPreferencia($hit, self::PREFERENCIA_MISMO_DIA);
        }

        if ($turno === RendicionMaquinaTurno::NOCHE) {
            $hit = self::queryBase($empresaId, $exceptoId)
                ->whereDate('fecha', $fechaYmd)
                ->whereIn('turno', [RendicionMaquinaTurno::TARDE, RendicionMaquinaTurno::MANIANA])
                ->orderByDesc('id')
                ->get();

            return self::elegirPorPreferencia($hit, self::PREFERENCIA_MISMO_DIA);
        }

        $fechaAnt = Carbon::parse($fechaYmd)->subDay()->format('Y-m-d');
        $anterioresDia = self::queryBase($empresaId, $exceptoId)
            ->whereDate('fecha', $fechaAnt)
            ->whereIn('turno', array_keys(self::PREFERENCIA_CIERRE_DIA_ANT))
            ->orderByDesc('id')
            ->get();

        return self::elegirPorPreferencia($anterioresDia, self::PREFERENCIA_CIERRE_DIA_ANT);
    }

    /**
     * @param  Collection<int, RendicionMaquina>  $filas
     * @param  array<string, int>  $preferencia
     */
    private static function elegirPorPreferencia(Collection $filas, array $preferencia): ?RendicionMaquina
    {
        if ($filas->isEmpty()) {
            return null;
        }

        return $filas->sortBy(function (RendicionMaquina $r) use ($preferencia) {
            $pref = $preferencia[$r->turno] ?? 99;

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

    private static function sumaTransferenciasDiaAnteriorParciales(
        int $empresaId,
        string $fechaYmd,
        ?int $exceptoId
    ): float {
        $fechaAnt = Carbon::parse($fechaYmd)->subDay()->format('Y-m-d');

        return round((float) self::queryBase($empresaId, $exceptoId)
            ->whereDate('fecha', $fechaAnt)
            ->whereIn('turno', [
                RendicionMaquinaTurno::MANIANA,
                RendicionMaquinaTurno::TARDE,
                RendicionMaquinaTurno::NOCHE,
            ])
            ->sum('transferencia'), 2);
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
