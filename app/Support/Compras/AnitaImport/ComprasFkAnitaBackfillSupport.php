<?php

namespace App\Support\Compras\AnitaImport;

use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de FKs compras 2025 (y rangos) desde Anita / claves locales.
 * No toca cuenta corriente.
 */
final class ComprasFkAnitaBackfillSupport
{
    /**
     * @return array{
     *   mapa_com_oc: array<string, int>,
     *   oc_ids: array<string, int>,
     *   com_oc_erp: array<string, int>
     * }
     */
    public static function cargarIndicesOc(string $desde, string $hasta): array
    {
        $ocIds = [];
        $ocIdsPorNro = [];
        foreach (DB::table('ordencompra')->select('id', 'empresa_id', 'numeroordencompra')->cursor() as $oc) {
            $ocIds[(int) $oc->empresa_id.'|'.(int) $oc->numeroordencompra] = (int) $oc->id;
            $ocIdsPorNro[(int) $oc->numeroordencompra][] = (int) $oc->id;
        }

        $comOcAnita = [];
        $fechaDesdeA = RecepcionProveedorAnitaImportSupport::fechaAnitaDesde($desde);
        $fechaHastaA = RecepcionProveedorAnitaImportSupport::fechaAnitaDesde($hasta);
        $y = (int) substr($desde, 0, 4);
        $m = (int) substr($desde, 5, 2);
        $yH = (int) substr($hasta, 0, 4);
        $mH = (int) substr($hasta, 5, 2);

        while ($y < $yH || ($y === $yH && $m <= $mH)) {
            $fd = (int) sprintf('%04d%02d01', $y, $m);
            $ultimo = (int) date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));
            $fh = (int) sprintf('%04d%02d%02d', $y, $m, $ultimo);
            if ($fd < $fechaDesdeA) {
                $fd = $fechaDesdeA;
            }
            if ($fh > $fechaHastaA) {
                $fh = $fechaHastaA;
            }
            foreach (RecepcionProveedorAnitaImportSupport::listarRecepmae($fd, $fh) as $cab) {
                $suc = (int) ($cab->recm_sucursal ?? 0);
                $nro = (int) ($cab->recm_nro ?? 0);
                $nroOc = RecepcionProveedorAnitaImportSupport::numeroOrdencompraDesdeCabecera($cab);
                if ($suc > 0 && $nro > 0 && $nroOc > 0) {
                    $comOcAnita[$suc.'|'.$nro] = $nroOc;
                }
            }
            $m++;
            if ($m > 12) {
                $m = 1;
                $y++;
            }
        }

        $comOcErp = [];
        foreach (DB::table('recepcion_proveedor as r')
            ->join('ordencompra as o', 'o.id', '=', 'r.ordencompra_id')
            ->whereNotNull('r.anita_nro')
            ->whereNotNull('r.ordencompra_id')
            ->get(['r.anita_sucursal', 'r.anita_nro', 'o.numeroordencompra']) as $row) {
            $comOcErp[(int) $row->anita_sucursal.'|'.(int) $row->anita_nro] = (int) $row->numeroordencompra;
        }

        return [
            'mapa_com_oc' => $comOcAnita,
            'oc_ids' => $ocIds,
            'oc_ids_por_nro' => $ocIdsPorNro,
            'com_oc_erp' => $comOcErp,
        ];
    }

    /**
     * @param  array<string, int>  $mapaComOc
     * @param  array<string, int>  $ocIds
     * @param  array<int, list<int>>  $ocIdsPorNro
     * @return array{vincular: list<array{rec_id: int, ordencompra_id: int, nro_oc: int, via: string}>, stats: array<string, int>}
     */
    public static function planRecepcionOc(
        string $desde,
        string $hasta,
        array $mapaComOc,
        array $ocIds,
        array $ocIdsPorNro = [],
    ): array {
        $stats = [
            'candidatas' => 0,
            'vincular' => 0,
            'via_empresa' => 0,
            'via_cross_empresa' => 0,
            'oc_faltante_erp' => 0,
            'sin_oc_anita' => 0,
        ];
        $vincular = [];

        $recs = DB::table('recepcion_proveedor')
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereNull('ordencompra_id')
            ->whereNotNull('anita_nro')
            ->get(['id', 'empresa_id', 'anita_sucursal', 'anita_nro']);

        $stats['candidatas'] = $recs->count();

        foreach ($recs as $r) {
            $clave = (int) $r->anita_sucursal.'|'.(int) $r->anita_nro;
            $nroOc = (int) ($mapaComOc[$clave] ?? 0);
            if ($nroOc <= 0) {
                $stats['sin_oc_anita']++;

                continue;
            }
            $ocId = $ocIds[(int) $r->empresa_id.'|'.$nroOc] ?? null;
            $via = 'empresa';
            if ($ocId === null) {
                $cands = $ocIdsPorNro[$nroOc] ?? [];
                if (count($cands) === 1) {
                    $ocId = $cands[0];
                    $via = 'cross_empresa';
                }
            }
            if ($ocId === null) {
                $stats['oc_faltante_erp']++;

                continue;
            }
            $stats['vincular']++;
            if ($via === 'empresa') {
                $stats['via_empresa']++;
            } else {
                $stats['via_cross_empresa']++;
            }
            $vincular[] = [
                'rec_id' => (int) $r->id,
                'ordencompra_id' => $ocId,
                'nro_oc' => $nroOc,
                'via' => $via,
            ];
        }

        return ['vincular' => $vincular, 'stats' => $stats];
    }

    /**
     * @param  array<string, int>  $mapaComOc
     * @param  array<string, int>  $ocIds
     * @param  array<string, int>  $comOcErp
     * @return array{vincular: list<array{cp_id: int, ordencompra_id: int, nro_oc: int, via: string}>, stats: array<string, int|list<string>>}
     */
    public static function planCpOc(
        string $desde,
        string $hasta,
        array $mapaComOc,
        array $ocIds,
        array $comOcErp,
    ): array {
        $stats = [
            'candidatas' => 0,
            'vincular' => 0,
            'via_pep' => 0,
            'via_com' => 0,
            'oc_faltante_erp' => 0,
            'sin_aplicped' => 0,
            'sin_proveedor' => 0,
            'errores_bridge' => [],
        ];
        $vincular = [];

        $cps = DB::table('comprobante_proveedor as cp')
            ->join('tipotransaccion_compra as tc', 'tc.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'cp.proveedor_id')
            ->whereBetween('cp.fechacomprobante', [$desde, $hasta])
            ->where('cp.origen_entrada', 'ANITA_IMPORT')
            ->whereNull('cp.ordencompra_id')
            ->get([
                'cp.id',
                'cp.empresa_id',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
                'tc.abreviatura',
                'p.codigo as proveedor_codigo',
            ]);

        $stats['candidatas'] = $cps->count();

        $facturas = [];
        foreach ($cps as $cp) {
            $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($cp->proveedor_codigo ?? ''));
            $tipo = strtoupper(trim((string) $cp->abreviatura));
            $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) $cp->letra);
            $suc = (int) $cp->sucursal;
            $nro = (int) $cp->numerocomprobante;
            if ($prov === '' || $tipo === '' || $nro <= 0) {
                continue;
            }
            $facturas[] = [$prov, $tipo, $letra, $suc, $nro];
        }

        $errores = [];
        $reader = new MayorConceptoAnitaBridgeReader;
        $aplicpedRows = $reader->cargarAplicpedPorFacturas($facturas, $errores);
        $stats['errores_bridge'] = $errores;

        $refsPorFac = [];
        foreach ($aplicpedRows as $apl) {
            $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($apl->aplp_proveedor ?? ''));
            $tipo = strtoupper(trim((string) ($apl->aplp_tipo ?? '')));
            $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($apl->aplp_letra ?? ''));
            $suc = (int) ($apl->aplp_sucursal ?? 0);
            $nro = (int) ($apl->aplp_nro ?? 0);
            $clave = $prov.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro;
            $refsPorFac[$clave][] = [
                'ref_tipo' => strtoupper(trim((string) ($apl->aplp_ref_tipo ?? ''))),
                'ref_nro' => (int) ($apl->aplp_ref_nro ?? 0),
                'ref_suc' => (int) ($apl->aplp_ref_sucursal ?? 0),
            ];
        }

        foreach ($cps as $cp) {
            $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($cp->proveedor_codigo ?? ''));
            if ($prov === '') {
                $stats['sin_proveedor']++;

                continue;
            }
            $tipo = strtoupper(trim((string) $cp->abreviatura));
            $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) $cp->letra);
            $suc = (int) $cp->sucursal;
            $nro = (int) $cp->numerocomprobante;
            $clave = $prov.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro;
            $refs = $refsPorFac[$clave] ?? [];
            if ($refs === []) {
                $stats['sin_aplicped']++;

                continue;
            }

            $nrosOc = [];
            $via = null;
            foreach ($refs as $ref) {
                if ($ref['ref_tipo'] === 'PEP' && $ref['ref_nro'] > 0) {
                    $nrosOc[$ref['ref_nro']] = true;
                    $via = 'pep';
                }
            }
            if ($nrosOc === []) {
                foreach ($refs as $ref) {
                    if ($ref['ref_tipo'] !== 'COM' || $ref['ref_nro'] <= 0) {
                        continue;
                    }
                    $comKey = $ref['ref_suc'].'|'.$ref['ref_nro'];
                    $nroOc = (int) ($mapaComOc[$comKey] ?? ($comOcErp[$comKey] ?? 0));
                    if ($nroOc > 0) {
                        $nrosOc[$nroOc] = true;
                        $via = 'com';
                    }
                }
            }
            if ($nrosOc === []) {
                continue;
            }

            $nroOc = (int) array_key_first($nrosOc);
            $ocId = $ocIds[(int) $cp->empresa_id.'|'.$nroOc] ?? null;
            if ($ocId === null) {
                $stats['oc_faltante_erp']++;

                continue;
            }
            if ($via === 'pep') {
                $stats['via_pep']++;
            } elseif ($via === 'com') {
                $stats['via_com']++;
            }
            $stats['vincular']++;
            $vincular[] = [
                'cp_id' => (int) $cp->id,
                'ordencompra_id' => $ocId,
                'nro_oc' => $nroOc,
                'via' => (string) $via,
            ];
        }

        return ['vincular' => $vincular, 'stats' => $stats];
    }

    /**
     * @return array{
     *   vincular_cp: list<array{asiento_id: int, cp_id: int}>,
     *   vincular_rec: list<array{asiento_id: int, recepcion_id: int}>,
     *   stats: array<string, int>
     * }
     */
    /**
     * @return array{
     *   vincular_cp: list<array{asiento_id: int, cp_id: int}>,
     *   vincular_rec: list<array{asiento_id: int, recepcion_id: int}>,
     *   stats: array<string, int>
     * }
     */
    public static function planAsientoDocs(string $desde, string $hasta): array
    {
        $stats = [
            'candidatos' => 0,
            'vincular_cp' => 0,
            'vincular_rec' => 0,
            'via_emisor' => 0,
            'ambiguo_cp' => 0,
            'ambiguo_rec' => 0,
            'sin_match' => 0,
        ];
        $vincularCp = [];
        $vincularRec = [];

        $cpIndex = [];
        $cpIndexProv = [];
        foreach (DB::table('comprobante_proveedor as cp')
            ->join('tipotransaccion_compra as tc', 'tc.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'cp.proveedor_id')
            ->whereBetween('cp.fechacomprobante', [$desde, $hasta])
            ->where('cp.origen_entrada', 'ANITA_IMPORT')
            ->get([
                'cp.id', 'cp.empresa_id', 'cp.letra', 'cp.sucursal', 'cp.numerocomprobante',
                'cp.asiento_id', 'tc.abreviatura', 'p.codigo as proveedor_codigo',
            ]) as $cp) {
            $k = (int) $cp->empresa_id.'|'
                .strtoupper(trim((string) $cp->abreviatura)).'|'
                .trim((string) $cp->letra).'|'
                .(int) $cp->sucursal.'|'
                .(int) $cp->numerocomprobante;
            $cpIndex[$k][] = $cp;
            $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($cp->proveedor_codigo ?? ''));
            if ($prov !== '') {
                $cpIndexProv[$k.'|'.$prov][] = $cp;
            }
        }

        $recIndex = [];
        foreach (DB::table('recepcion_proveedor')
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereNotNull('anita_nro')
            ->get(['id', 'empresa_id', 'anita_sucursal', 'anita_nro', 'anita_tipo', 'anita_letra', 'asiento_id']) as $r) {
            $k = (int) $r->empresa_id.'|'
                .strtoupper(trim((string) ($r->anita_tipo ?? 'COM'))).'|'
                .trim((string) ($r->anita_letra ?? 'X')).'|'
                .(int) $r->anita_sucursal.'|'
                .(int) $r->anita_nro;
            $recIndex[$k][] = $r;
        }

        $asientos = DB::table('asiento')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('anita_sistema', 'C')
            ->whereNull('comprobante_proveedor_id')
            ->whereNull('recepcionproveedor_id')
            ->whereNotNull('anita_tipo')
            ->whereNotNull('anita_nro')
            ->get(['id', 'empresa_id', 'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro', 'anita_emisor']);

        $stats['candidatos'] = $asientos->count();

        foreach ($asientos as $a) {
            $tipo = strtoupper(trim((string) $a->anita_tipo));
            $letra = trim((string) ($a->anita_letra ?? ''));
            $suc = (int) ($a->anita_sucursal ?? 0);
            $nro = (int) $a->anita_nro;
            $emp = (int) $a->empresa_id;
            $k = $emp.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro;

            if ($tipo === 'COM') {
                $hits = $recIndex[$k] ?? [];
                if (count($hits) === 1) {
                    $stats['vincular_rec']++;
                    $vincularRec[] = [
                        'asiento_id' => (int) $a->id,
                        'recepcion_id' => (int) $hits[0]->id,
                    ];
                } elseif (count($hits) > 1) {
                    $stats['ambiguo_rec']++;
                } else {
                    $stats['sin_match']++;
                }

                continue;
            }

            $hits = $cpIndex[$k] ?? [];
            $viaEmisor = false;
            if (count($hits) > 1) {
                $emisor = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($a->anita_emisor ?? ''));
                if ($emisor !== '' && isset($cpIndexProv[$k.'|'.$emisor])) {
                    $hits = $cpIndexProv[$k.'|'.$emisor];
                    $viaEmisor = true;
                }
            }
            if (count($hits) === 1) {
                $stats['vincular_cp']++;
                if ($viaEmisor) {
                    $stats['via_emisor']++;
                }
                $vincularCp[] = [
                    'asiento_id' => (int) $a->id,
                    'cp_id' => (int) $hits[0]->id,
                ];
            } elseif (count($hits) > 1) {
                $stats['ambiguo_cp']++;
            } else {
                $stats['sin_match']++;
            }
        }

        return [
            'vincular_cp' => $vincularCp,
            'vincular_rec' => $vincularRec,
            'stats' => $stats,
        ];
    }

    /**
     * Asiento sistema T (OPP/OPA) ↔ pagoproveedor.
     *
     * Un mismo OPP suele generar más de un `nro_operacion` en subdiario; se acepta
     * 1 pago → N asientos. Si hay varios pagos con la misma clave, se omite (ambiguo).
     *
     * @return array{vincular: list<array{asiento_id: int, pago_id: int}>, stats: array<string, int>}
     */
    public static function planAsientoPagos(string $desde, string $hasta): array
    {
        $stats = [
            'candidatos' => 0,
            'vincular' => 0,
            'ambiguo' => 0,
            'sin_match' => 0,
        ];
        $vincular = [];

        $pagoIndex = [];
        foreach (DB::table('pagoproveedor')
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereIn('tipocomprobante', ['OPP', 'OPA'])
            ->get(['id', 'empresa_id', 'tipocomprobante', 'letra', 'sucursal', 'numerotransaccion']) as $p) {
            $k = (int) $p->empresa_id.'|'
                .strtoupper(trim((string) $p->tipocomprobante)).'|'
                .trim((string) $p->letra).'|'
                .(int) $p->sucursal.'|'
                .(int) $p->numerotransaccion;
            $pagoIndex[$k][] = $p;
        }

        $asientos = DB::table('asiento')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('anita_sistema', 'T')
            ->whereIn('anita_tipo', ['OPP', 'OPA'])
            ->whereNull('pagoproveedor_id')
            ->whereNotNull('anita_nro')
            ->get(['id', 'empresa_id', 'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro']);

        $stats['candidatos'] = $asientos->count();

        $asiPorClave = [];
        foreach ($asientos as $a) {
            $k = (int) $a->empresa_id.'|'
                .strtoupper(trim((string) $a->anita_tipo)).'|'
                .trim((string) ($a->anita_letra ?? '')).'|'
                .(int) ($a->anita_sucursal ?? 0).'|'
                .(int) $a->anita_nro;
            $asiPorClave[$k][] = $a;
        }

        foreach ($asiPorClave as $k => $asis) {
            $pagos = $pagoIndex[$k] ?? [];
            // 1 pago + N asientos (típico: dos nro_operacion del mismo OPP en subdiario):
            // se vinculan todos al mismo pagoproveedor. Solo se omite si hay varios pagos.
            if (count($pagos) === 1 && count($asis) >= 1) {
                $pagoId = (int) $pagos[0]->id;
                foreach ($asis as $a) {
                    $stats['vincular']++;
                    $vincular[] = [
                        'asiento_id' => (int) $a->id,
                        'pago_id' => $pagoId,
                    ];
                }
            } elseif ($pagos === []) {
                $stats['sin_match'] += count($asis);
            } else {
                $stats['ambiguo']++;
            }
        }

        return ['vincular' => $vincular, 'stats' => $stats];
    }

    public static function aplicarRecepcionOc(array $filas): int
    {
        $n = 0;
        foreach (array_chunk($filas, 500) as $chunk) {
            foreach ($chunk as $f) {
                $n += DB::table('recepcion_proveedor')
                    ->where('id', $f['rec_id'])
                    ->whereNull('ordencompra_id')
                    ->update([
                        'ordencompra_id' => $f['ordencompra_id'],
                        'updated_at' => now(),
                    ]);
            }
        }

        return $n;
    }

    /**
     * @param  list<array{cp_id: int, ordencompra_id: int}>  $filas
     */
    public static function aplicarCpOc(array $filas): int
    {
        $n = 0;
        foreach (array_chunk($filas, 500) as $chunk) {
            foreach ($chunk as $f) {
                $n += DB::table('comprobante_proveedor')
                    ->where('id', $f['cp_id'])
                    ->whereNull('ordencompra_id')
                    ->update([
                        'ordencompra_id' => $f['ordencompra_id'],
                        'updated_at' => now(),
                    ]);
            }
        }

        return $n;
    }

    /**
     * @param  list<array{asiento_id: int, cp_id: int}>  $filasCp
     * @param  list<array{asiento_id: int, recepcion_id: int}>  $filasRec
     * @return array{asientos_cp: int, asientos_rec: int, cp_asiento: int, rec_asiento: int}
     */
    public static function aplicarAsientoDocs(array $filasCp, array $filasRec): array
    {
        $out = ['asientos_cp' => 0, 'asientos_rec' => 0, 'cp_asiento' => 0, 'rec_asiento' => 0];

        foreach (array_chunk($filasCp, 500) as $chunk) {
            foreach ($chunk as $f) {
                $out['asientos_cp'] += DB::table('asiento')
                    ->where('id', $f['asiento_id'])
                    ->whereNull('comprobante_proveedor_id')
                    ->whereNull('recepcionproveedor_id')
                    ->update([
                        'comprobante_proveedor_id' => $f['cp_id'],
                        'updated_at' => now(),
                    ]);
                $out['cp_asiento'] += DB::table('comprobante_proveedor')
                    ->where('id', $f['cp_id'])
                    ->whereNull('asiento_id')
                    ->update([
                        'asiento_id' => $f['asiento_id'],
                        'updated_at' => now(),
                    ]);
            }
        }

        foreach (array_chunk($filasRec, 500) as $chunk) {
            foreach ($chunk as $f) {
                $out['asientos_rec'] += DB::table('asiento')
                    ->where('id', $f['asiento_id'])
                    ->whereNull('comprobante_proveedor_id')
                    ->whereNull('recepcionproveedor_id')
                    ->update([
                        'recepcionproveedor_id' => $f['recepcion_id'],
                        'updated_at' => now(),
                    ]);
                $out['rec_asiento'] += DB::table('recepcion_proveedor')
                    ->where('id', $f['recepcion_id'])
                    ->whereNull('asiento_id')
                    ->update([
                        'asiento_id' => $f['asiento_id'],
                        'updated_at' => now(),
                    ]);
            }
        }

        return $out;
    }

    /**
     * @param  list<array{asiento_id: int, pago_id: int}>  $filas
     * @return array{asientos: int, pagos: int}
     */
    public static function aplicarAsientoPagos(array $filas): array
    {
        $out = ['asientos' => 0, 'pagos' => 0];
        foreach (array_chunk($filas, 500) as $chunk) {
            foreach ($chunk as $f) {
                $out['asientos'] += DB::table('asiento')
                    ->where('id', $f['asiento_id'])
                    ->whereNull('pagoproveedor_id')
                    ->update([
                        'pagoproveedor_id' => $f['pago_id'],
                        'updated_at' => now(),
                    ]);
                $out['pagos'] += DB::table('pagoproveedor')
                    ->where('id', $f['pago_id'])
                    ->whereNull('asiento_id')
                    ->update([
                        'asiento_id' => $f['asiento_id'],
                        'updated_at' => now(),
                    ]);
            }
        }

        return $out;
    }
}
