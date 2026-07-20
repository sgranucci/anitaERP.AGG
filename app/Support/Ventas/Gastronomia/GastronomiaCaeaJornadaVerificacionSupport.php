<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verificación de integridad de comprobantes CAEA por jornada (solo lectura).
 *
 * Complementa a los conciliadores de siempre (Anita / rendgastro / contabilidad): controla lo
 * específico del failover CAEA que aquellos no exponen de forma directa:
 *  - Toda venta emitida en un PV CAEA compartido tiene número CAEA y emisión (gastronomía o
 *    estacionamiento); ninguna queda huérfana.
 *  - El número CAEA usado coincide con el `arca_caea` vigente de la empresa/quincena.
 *  - Cada CAEA de salón tiene `identificador_pc` (base del Z por PC; sin él no suma al Z − NC).
 *  - Atribución del PV CAEA compartido entre gastronomía y estacionamiento (no infla el Z de salón).
 *
 * No consulta Anita ni escribe nada: apto para correr al cierre de jornada.
 */
final class GastronomiaCaeaJornadaVerificacionSupport
{
    /**
     * @return array<string, mixed>
     */
    public function verificar(int $empresaId, string $fechaJornada): array
    {
        $fecha = Carbon::parse($fechaJornada)->toDateString();

        $empresaNombre = (string) (DB::table('empresa')->where('id', $empresaId)->value('nombre') ?? '');
        $jornadaEstado = DB::table('jornada_gastronomia')
            ->where('empresa_id', $empresaId)
            ->where('fecha_jornada', $fecha)
            ->value('estado');

        $caeaPvs = DB::table('configuracion_puntoventa_gastronomia as cpg')
            ->join('puntoventa as pv', 'pv.id', '=', 'cpg.puntoventa_caea_id')
            ->where('cpg.empresa_id', $empresaId)
            ->whereNotNull('cpg.puntoventa_caea_id')
            ->distinct()
            ->pluck('pv.codigo', 'pv.id')
            ->map(static fn ($c): string => (string) $c)
            ->all();

        $base = [
            'empresa_id' => $empresaId,
            'empresa_nombre' => $empresaNombre,
            'fecha_jornada' => $fecha,
            'jornada_estado' => $jornadaEstado !== null ? (string) $jornadaEstado : null,
            'caea_pvs' => $caeaPvs,
            'caea_vigente' => $this->caeaVigente($empresaId, $fecha),
            'ventas_caea' => [
                'total_cant' => 0, 'total_monto' => 0.0, 'sin_cae' => 0,
                'gastro' => ['cant' => 0, 'monto' => 0.0, 'nc_cant' => 0, 'nc_monto' => 0.0],
                'estacionamiento' => ['cant' => 0, 'monto' => 0.0],
                'huerfanas' => [], 'numero_no_coincide' => [], 'informado_pendiente' => 0,
            ],
            'por_pc_gastro' => [],
            'gastro_sin_pc' => 0,
            'problemas' => [],
            'ok' => true,
        ];

        if ($caeaPvs === []) {
            return $base;
        }

        $pvIds = array_map('intval', array_keys($caeaPvs));

        $ventas = DB::table('venta as v')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->leftJoin('venta_gastronomia_emision as g', 'g.venta_id', '=', 'v.id')
            ->leftJoin('venta_estacionamiento_emision as e', 'e.venta_id', '=', 'v.id')
            ->whereIn('v.puntoventa_id', $pvIds)
            ->where(function ($q) use ($fecha) {
                $q->whereDate('v.fechajornada', $fecha)
                    ->orWhere(function ($legacy) use ($fecha) {
                        $legacy->whereNull('v.fechajornada')->whereDate('v.fecha', $fecha);
                    });
            })
            ->get([
                'v.id', 'v.puntoventa_id', 'pv.codigo as pv_cod', 'v.total', 'v.cae',
                'v.caea_informado_estado', 'v.created_at',
                'g.venta_id as g_vid', 'g.identificador_pc as g_pc', 'g.venta_factura_origen_id as g_nc_origen',
                'e.venta_id as e_vid', 'e.identificador_pc as e_pc',
            ]);

        $vc = &$base['ventas_caea'];
        $caeaVigenteNro = $base['caea_vigente']['nro_caea'] ?? null;
        $porPc = [];

        foreach ($ventas as $r) {
            $monto = round((float) $r->total, 2);
            $vc['total_cant']++;
            $vc['total_monto'] = round($vc['total_monto'] + $monto, 2);

            if ($r->cae === null || trim((string) $r->cae) === '') {
                $vc['sin_cae']++;
            } elseif ($caeaVigenteNro !== null && (string) $r->cae !== (string) $caeaVigenteNro) {
                $vc['numero_no_coincide'][] = ['venta_id' => (int) $r->id, 'cae_usado' => (string) $r->cae, 'pv' => (string) $r->pv_cod];
            }

            if (trim((string) $r->caea_informado_estado) === '') {
                $vc['informado_pendiente']++;
            }

            $esGastro = $r->g_vid !== null;
            $esEstac = ! $esGastro && $r->e_vid !== null;

            if ($esGastro) {
                $esNc = $r->g_nc_origen !== null;
                $vc['gastro']['cant']++;
                $vc['gastro']['monto'] = round($vc['gastro']['monto'] + $monto, 2);
                if ($esNc) {
                    $vc['gastro']['nc_cant']++;
                    $vc['gastro']['nc_monto'] = round($vc['gastro']['nc_monto'] + abs($monto), 2);
                }
                $pc = trim((string) $r->g_pc);
                if ($pc === '') {
                    $base['gastro_sin_pc']++;
                } else {
                    $porPc[$pc] ??= ['identificador_pc' => $pc, 'pv' => (string) $r->pv_cod, 'cant' => 0, 'monto' => 0.0, 'nc_cant' => 0, 'nc_monto' => 0.0];
                    $porPc[$pc]['cant']++;
                    $porPc[$pc]['monto'] = round($porPc[$pc]['monto'] + $monto, 2);
                    if ($esNc) {
                        $porPc[$pc]['nc_cant']++;
                        $porPc[$pc]['nc_monto'] = round($porPc[$pc]['nc_monto'] + abs($monto), 2);
                    }
                }
            } elseif ($esEstac) {
                $vc['estacionamiento']['cant']++;
                $vc['estacionamiento']['monto'] = round($vc['estacionamiento']['monto'] + $monto, 2);
            } else {
                $vc['huerfanas'][] = [
                    'venta_id' => (int) $r->id, 'pv' => (string) $r->pv_cod,
                    'monto' => $monto, 'created_at' => (string) $r->created_at,
                ];
            }
        }
        unset($vc);

        ksort($porPc);
        $base['por_pc_gastro'] = array_values($porPc);

        $base['problemas'] = $this->detectarProblemas($base);
        $base['ok'] = ! $this->tieneErrores($base['problemas']);

        return $base;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function caeaVigente(int $empresaId, string $fecha): ?array
    {
        $row = DB::table('arca_caea')
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_vigencia_desde', '<=', $fecha)
            ->whereDate('fecha_vigencia_hasta', '>=', $fecha)
            ->orderByDesc('id')
            ->first(['periodo', 'nro_caea', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', 'estado', 'informe_estado']);

        if ($row === null) {
            return null;
        }

        return [
            'periodo' => (string) $row->periodo,
            'nro_caea' => (string) $row->nro_caea,
            'vig_desde' => (string) $row->fecha_vigencia_desde,
            'vig_hasta' => (string) $row->fecha_vigencia_hasta,
            'estado' => (string) $row->estado,
            'informe_estado' => (string) $row->informe_estado,
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<array{nivel:string,texto:string}>
     */
    private function detectarProblemas(array $r): array
    {
        $p = [];
        $vc = $r['ventas_caea'];

        if ((int) $vc['sin_cae'] > 0) {
            $p[] = ['nivel' => 'ERROR', 'texto' => sprintf('%d venta(s) CAEA sin número CAEA asignado.', $vc['sin_cae'])];
        }
        if ($vc['huerfanas'] !== []) {
            $ids = implode(', ', array_map(static fn ($h) => $h['venta_id'], $vc['huerfanas']));
            $p[] = ['nivel' => 'ERROR', 'texto' => sprintf('%d venta(s) CAEA sin emisión gastronomía ni estacionamiento (huérfanas): %s.', count($vc['huerfanas']), $ids)];
        }
        if ((int) $r['gastro_sin_pc'] > 0) {
            $p[] = ['nivel' => 'ERROR', 'texto' => sprintf('%d CAEA de salón sin identificador_pc: no sumarán al Z por PC (Z − NC).', $r['gastro_sin_pc'])];
        }
        if ($vc['numero_no_coincide'] !== []) {
            $ids = implode(', ', array_map(static fn ($h) => $h['venta_id'], $vc['numero_no_coincide']));
            $p[] = ['nivel' => 'WARN', 'texto' => sprintf('%d venta(s) usan un CAEA distinto al vigente de la quincena: %s.', count($vc['numero_no_coincide']), $ids)];
        }
        if ($vc['total_cant'] > 0 && $r['caea_vigente'] === null) {
            $p[] = ['nivel' => 'WARN', 'texto' => 'Hay ventas CAEA pero no hay arca_caea vigente cargado para la fecha.'];
        }
        if ($r['caea_vigente'] !== null && strtolower((string) $r['caea_vigente']['informe_estado']) !== 'ok') {
            $p[] = ['nivel' => 'WARN', 'texto' => sprintf('CAEA vigente con informe_estado=%s (revisar obtención del CAEA).', $r['caea_vigente']['informe_estado'])];
        }

        return $p;
    }

    /**
     * @param  list<array{nivel:string,texto:string}>  $problemas
     */
    private function tieneErrores(array $problemas): bool
    {
        foreach ($problemas as $p) {
            if (($p['nivel'] ?? '') === 'ERROR') {
                return true;
            }
        }

        return false;
    }
}
