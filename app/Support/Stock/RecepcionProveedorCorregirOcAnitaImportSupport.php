<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Reasigna recepcion_proveedor.ordencompra_id cuando el import Anita usó
 * recm_com_nro (DEP) en lugar de recm_nro_fac / aplicped (PEP real).
 *
 * Caso típico: COM 2025-2026 colgadas de OCs históricas (2008) cuyo número
 * coincide con el DEP de recepmae.
 */
final class RecepcionProveedorCorregirOcAnitaImportSupport
{
    /**
     * @param  list<int>  $comsFiltro  Si no vacío, solo esas COM (anita_nro)
     * @return array{
     *   stats: array<string, int>,
     *   vincular: list<array{rp_id: int, com: int, oc_id_actual: int, nro_oc_actual: int, nro_oc_anita: int, oc_id_nuevo: int, empresa_id: int}>,
     *   omitidas: list<array{rp_id: int, com: int, motivo: string, detalle?: string}>
     * }
     */
    public static function planificar(
        string $ocFechaHasta = '2014-12-31',
        string $rpFechaDesde = '2025-01-01',
        array $comsFiltro = [],
    ): array {
        $stats = [
            'candidatas' => 0,
            'ya_ok' => 0,
            'vincular' => 0,
            'sin_pep_anita' => 0,
            'oc_faltante_erp' => 0,
            'sin_anita_nro' => 0,
        ];
        $vincular = [];
        $omitidas = [];

        $q = DB::table('recepcion_proveedor as rp')
            ->join('ordencompra as oc', 'oc.id', '=', 'rp.ordencompra_id')
            ->where('rp.origen_carga', 'ANITA_IMPORT')
            ->where('oc.fecha', '<=', $ocFechaHasta)
            ->where('rp.fecha', '>=', $rpFechaDesde)
            ->whereNotNull('rp.ordencompra_id')
            ->select([
                'rp.id as rp_id',
                'rp.anita_sucursal',
                'rp.anita_nro',
                'rp.empresa_id',
                'rp.ordencompra_id as oc_id_actual',
                'oc.numeroordencompra as nro_oc_actual',
            ])
            ->orderBy('rp.id');

        if ($comsFiltro !== []) {
            $q->whereIn('rp.anita_nro', array_map('intval', $comsFiltro));
        }

        $filas = $q->get();
        $stats['candidatas'] = $filas->count();

        $clavesCom = [];
        foreach ($filas as $f) {
            $suc = (int) ($f->anita_sucursal ?? 1);
            $nro = (int) ($f->anita_nro ?? 0);
            if ($nro <= 0) {
                $stats['sin_anita_nro']++;
                $omitidas[] = [
                    'rp_id' => (int) $f->rp_id,
                    'com' => 0,
                    'motivo' => 'sin_anita_nro',
                ];

                continue;
            }
            $clavesCom[] = $suc.'-'.$nro;
        }

        $mapaAnita = RecepcionProveedorAnitaImportSupport::mapaOcDesdeAplicpedPorCom(
            array_values(array_unique($clavesCom))
        );

        $ocIdsPorEmpresaNro = [];
        $ocIdsPorNro = [];
        foreach (DB::table('ordencompra')->select('id', 'empresa_id', 'numeroordencompra')->cursor() as $oc) {
            $nro = (int) $oc->numeroordencompra;
            $ocIdsPorEmpresaNro[(int) $oc->empresa_id.'|'.$nro] = (int) $oc->id;
            $ocIdsPorNro[$nro][] = (int) $oc->id;
        }

        foreach ($filas as $f) {
            $rpId = (int) $f->rp_id;
            $suc = (int) ($f->anita_sucursal ?? 1);
            $com = (int) ($f->anita_nro ?? 0);
            if ($com <= 0) {
                continue;
            }

            $clave = $suc.'-'.$com;
            $pep = (int) ($mapaAnita[$clave]['numero_oc'] ?? 0);
            if ($pep <= 0) {
                $stats['sin_pep_anita']++;
                $omitidas[] = [
                    'rp_id' => $rpId,
                    'com' => $com,
                    'motivo' => 'sin_pep_anita',
                    'detalle' => $clave,
                ];

                continue;
            }

            $nroActual = (int) $f->nro_oc_actual;
            if ($pep === $nroActual) {
                $stats['ya_ok']++;

                continue;
            }

            $empresaId = (int) $f->empresa_id;
            $ocIdNuevo = $ocIdsPorEmpresaNro[$empresaId.'|'.$pep]
                ?? ((count($ocIdsPorNro[$pep] ?? []) === 1) ? $ocIdsPorNro[$pep][0] : null);

            if ($ocIdNuevo === null) {
                $stats['oc_faltante_erp']++;
                $omitidas[] = [
                    'rp_id' => $rpId,
                    'com' => $com,
                    'motivo' => 'oc_faltante_erp',
                    'detalle' => "pep={$pep} empresa={$empresaId}",
                ];

                continue;
            }

            $stats['vincular']++;
            $vincular[] = [
                'rp_id' => $rpId,
                'com' => $com,
                'oc_id_actual' => (int) $f->oc_id_actual,
                'nro_oc_actual' => $nroActual,
                'nro_oc_anita' => $pep,
                'oc_id_nuevo' => (int) $ocIdNuevo,
                'empresa_id' => $empresaId,
            ];
        }

        return [
            'stats' => $stats,
            'vincular' => $vincular,
            'omitidas' => $omitidas,
        ];
    }

    /**
     * @param  list<array{rp_id: int, oc_id_nuevo: int}>  $filas
     */
    public static function aplicar(array $filas): int
    {
        $n = 0;
        foreach ($filas as $f) {
            $updated = DB::table('recepcion_proveedor')
                ->where('id', (int) $f['rp_id'])
                ->where('ordencompra_id', '!=', (int) $f['oc_id_nuevo'])
                ->update([
                    'ordencompra_id' => (int) $f['oc_id_nuevo'],
                    'updated_at' => now(),
                ]);
            $n += (int) $updated;
        }

        return $n;
    }
}
