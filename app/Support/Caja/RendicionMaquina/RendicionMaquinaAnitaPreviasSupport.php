<?php

declare(strict_types=1);

namespace App\Support\Caja\RendicionMaquina;

use App\ApiAnita;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fallback Anita (rendmaquina) para previas de rendición.
 * Solo se usa cuando ERP no tiene el dato (ver RendicionMaquinaPreviasSupport).
 */
final class RendicionMaquinaAnitaPreviasSupport
{
    /**
     * Preferencia cierre día anterior (Anita pisa M→T→N→C ⇒ C gana).
     *
     * @var array<string, int>
     */
    private const PREFERENCIA_CIERRE = [
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
     * Fondo inicial = fondo_cierre − transferencia de la rendición previa Anita.
     *
     * @return array{fondo_inicial: float, transferencia: float, origen: string}|null
     */
    public static function fondoInicial(int $empresaId, string $fechaYmd, string $turno): ?array
    {
        $turno = RendicionMaquinaTurno::normalizar($turno);
        if (RendicionMaquinaTurno::esCompleto($turno)) {
            return [
                'fondo_inicial' => 0.0,
                'transferencia' => 0.0,
                'origen' => 'anita_completo_cero',
            ];
        }

        $filas = self::listarDia($empresaId, self::fechaBusquedaPrevias($fechaYmd, $turno));
        if ($filas === []) {
            return null;
        }

        $elegida = self::elegirPrevia($filas, $fechaYmd, $turno);
        if ($elegida === null) {
            return null;
        }

        $fondoCierre = round((float) ($elegida->rendm_fondo_cierre ?? 0), 2);
        // T/N: restar suma de transferencias de turnos previos del día (no solo la previa).
        // Así noche = fondo_cierre − (M+T), alineado a fondo diario − comprobantes acumulados.
        $transferencia = in_array($turno, [RendicionMaquinaTurno::TARDE, RendicionMaquinaTurno::NOCHE], true)
            ? self::sumaTransferenciasAnterioresMismoDia($filas, $turno)
            : round((float) ($elegida->rendm_transfer ?? 0), 2);
        if (abs($transferencia) < 0.00001) {
            $transferencia = round((float) ($elegida->rendm_transfer ?? 0), 2);
        }
        $fondo = round($fondoCierre - $transferencia, 2);

        return [
            'fondo_inicial' => $fondo,
            'transferencia' => $transferencia,
            'origen' => sprintf(
                'anita#%s turno %s',
                (string) ($elegida->rendm_nro_oper ?? ''),
                (string) ($elegida->rendm_turno ?? '')
            ),
        ];
    }

    /**
     * @param  list<object>  $filas
     */
    private static function sumaTransferenciasAnterioresMismoDia(array $filas, string $turno): float
    {
        $anteriores = match ($turno) {
            RendicionMaquinaTurno::TARDE => [RendicionMaquinaTurno::MANIANA],
            RendicionMaquinaTurno::NOCHE => [RendicionMaquinaTurno::MANIANA, RendicionMaquinaTurno::TARDE],
            default => [],
        };
        if ($anteriores === []) {
            return 0.0;
        }

        $suma = 0.0;
        foreach ($filas as $fila) {
            $t = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            if (! in_array($t, $anteriores, true)) {
                continue;
            }
            $suma += (float) ($fila->rendm_transfer ?? 0);
        }

        return round($suma, 2);
    }

    /**
     * Comprobante turno M = suma transferencias M+T+N del día anterior
     * (calcula_transferencia_anterior; no incluye el C).
     *
     * @return array{comprobante: float, origen: string}|null
     */
    public static function comprobanteManiana(int $empresaId, string $fechaYmd): ?array
    {
        $fechaAnt = Carbon::parse($fechaYmd)->subDay()->format('Y-m-d');
        $filas = self::listarDia($empresaId, $fechaAnt);
        if ($filas === []) {
            return null;
        }

        $suma = 0.0;
        $n = 0;
        foreach ($filas as $fila) {
            $t = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            if (! in_array($t, [
                RendicionMaquinaTurno::MANIANA,
                RendicionMaquinaTurno::TARDE,
                RendicionMaquinaTurno::NOCHE,
            ], true)) {
                continue;
            }
            $suma += (float) ($fila->rendm_transfer ?? 0);
            $n++;
        }

        if ($n === 0) {
            return null;
        }

        return [
            'comprobante' => round($suma, 2),
            'origen' => 'anita_transfer_mtn_'.$fechaAnt,
        ];
    }

    /**
     * Comprobante T/N = suma transferencias de turnos anteriores del mismo día
     * (calcula_comprobantes_del_dia).
     *
     * @return array{comprobante: float, origen: string}|null
     */
    public static function comprobanteParcialMismoDia(int $empresaId, string $fechaYmd, string $turno): ?array
    {
        $turno = RendicionMaquinaTurno::normalizar($turno);
        $anteriores = match ($turno) {
            RendicionMaquinaTurno::TARDE => [RendicionMaquinaTurno::MANIANA],
            RendicionMaquinaTurno::NOCHE => [RendicionMaquinaTurno::MANIANA, RendicionMaquinaTurno::TARDE],
            default => [],
        };
        if ($anteriores === []) {
            return null;
        }

        $filas = self::listarDia($empresaId, $fechaYmd);
        if ($filas === []) {
            return null;
        }

        $suma = 0.0;
        $n = 0;
        foreach ($filas as $fila) {
            $t = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            if (! in_array($t, $anteriores, true)) {
                continue;
            }
            $suma += (float) ($fila->rendm_transfer ?? 0);
            $n++;
        }

        if ($n === 0) {
            return null;
        }

        return [
            'comprobante' => round($suma, 2),
            'origen' => 'anita_comp_'.$turno.'_'.$fechaYmd,
        ];
    }

    /**
     * Impuesto drop del turno C del día anterior (M lo reutiliza; C lo recalcula).
     *
     * @return array{impuesto_drop: float, origen: string}|null
     */
    public static function impuestoDropDiaAnterior(int $empresaId, string $fechaYmd): ?array
    {
        $fechaAnt = Carbon::parse($fechaYmd)->subDay()->format('Y-m-d');
        $filas = self::listarDia($empresaId, $fechaAnt);
        $completo = null;
        foreach ($filas as $fila) {
            $t = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            if ($t === RendicionMaquinaTurno::COMPLETO) {
                $completo = $fila;
            }
        }
        if ($completo === null) {
            return null;
        }

        return [
            'impuesto_drop' => round((float) ($completo->rendm_imp_drop ?? 0), 2),
            'origen' => 'anita_c#'.(string) ($completo->rendm_nro_oper ?? ''),
        ];
    }

    /**
     * Drop anterior del C = neto rodillo / ruleta del M del mismo día
     * (lee_rendiciones_del_dia pisa el bruto WIGOS con DROP_BILL_RODILLO del M).
     *
     * @return array{drop_bill_ant: float, drop_rul_ant: float, origen: string}|null
     */
    public static function dropAnteriorDesdeManiana(int $empresaId, string $fechaYmd): ?array
    {
        $filas = self::listarDia($empresaId, $fechaYmd);
        $maniana = null;
        foreach ($filas as $fila) {
            $t = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            if ($t === RendicionMaquinaTurno::MANIANA) {
                $maniana = $fila;
            }
        }
        if ($maniana === null) {
            return null;
        }

        return [
            'drop_bill_ant' => round((float) ($maniana->rendm_dr_bill_rod ?? 0), 2),
            'drop_rul_ant' => round((float) ($maniana->rendm_drop_ruleta ?? 0), 2),
            'origen' => 'anita_m#'.(string) ($maniana->rendm_nro_oper ?? ''),
        ];
    }

    /**
     * @return list<object>
     */
    private static function listarDia(int $empresaId, string $fechaYmd): array
    {
        if ($empresaId <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd)) {
            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            return [];
        }

        $fechaEntera = (int) str_replace('-', '', $fechaYmd);

        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => (string) config('rendicion_maquina_anita.sistema', 'caja'),
                'tabla' => (string) config('rendicion_maquina_anita.tabla_cabecera', 'rendmaquina'),
                'campos' => 'rendm_nro_oper,rendm_fecha,rendm_empresa,rendm_turno,'
                    .'rendm_fondo_cierre,rendm_transfer,rendm_imp_drop,'
                    .'rendm_dr_bill_rod,rendm_drop_ruleta,rendm_drop_billete',
                'whereArmado' => ' WHERE rendm_empresa='.$empresaAnita
                    .' AND rendm_fecha='.$fechaEntera,
            ]);
            $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        } catch (Throwable $e) {
            Log::warning('RendicionMaquina previas Anita: '.$e->getMessage(), [
                'empresa_id' => $empresaId,
                'fecha' => $fechaYmd,
            ]);

            return [];
        }

        $out = [];
        foreach ($filas as $fila) {
            $out[] = is_object($fila) ? $fila : (object) $fila;
        }

        return $out;
    }

    private static function fechaBusquedaPrevias(string $fechaYmd, string $turno): string
    {
        if (in_array($turno, [RendicionMaquinaTurno::TARDE, RendicionMaquinaTurno::NOCHE], true)) {
            return $fechaYmd;
        }

        return Carbon::parse($fechaYmd)->subDay()->format('Y-m-d');
    }

    /**
     * @param  list<object>  $filas
     */
    private static function elegirPrevia(array $filas, string $fechaYmd, string $turno): ?object
    {
        if (in_array($turno, [RendicionMaquinaTurno::TARDE, RendicionMaquinaTurno::NOCHE], true)) {
            $permitidos = $turno === RendicionMaquinaTurno::TARDE
                ? [RendicionMaquinaTurno::MANIANA]
                : [RendicionMaquinaTurno::TARDE, RendicionMaquinaTurno::MANIANA];

            return self::elegirPorPreferencia($filas, $permitidos, self::PREFERENCIA_MISMO_DIA);
        }

        // Mañana: C del día anterior (paridad Anita último del día)
        return self::elegirPorPreferencia(
            $filas,
            array_keys(self::PREFERENCIA_CIERRE),
            self::PREFERENCIA_CIERRE
        );
    }

    /**
     * @param  list<object>  $filas
     * @param  list<string>  $permitidos
     * @param  array<string, int>  $preferencia
     */
    private static function elegirPorPreferencia(array $filas, array $permitidos, array $preferencia): ?object
    {
        $candidatas = [];
        foreach ($filas as $fila) {
            $t = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            if (in_array($t, $permitidos, true)) {
                $candidatas[] = $fila;
            }
        }
        if ($candidatas === []) {
            return null;
        }

        usort($candidatas, function ($a, $b) use ($preferencia) {
            $ta = strtoupper(trim((string) ($a->rendm_turno ?? '')));
            $tb = strtoupper(trim((string) ($b->rendm_turno ?? '')));
            $pa = $preferencia[$ta] ?? 99;
            $pb = $preferencia[$tb] ?? 99;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return ((int) ($b->rendm_nro_oper ?? 0)) <=> ((int) ($a->rendm_nro_oper ?? 0));
        });

        return $candidatas[0];
    }
}
