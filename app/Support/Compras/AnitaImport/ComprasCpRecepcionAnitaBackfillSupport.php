<?php

namespace App\Support\Compras\AnitaImport;

use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use Illuminate\Support\Facades\DB;

/**
 * Backfill del pivot comprobante_proveedor_recepcion desde Anita (aplicped).
 *
 * Solo ANITA_IMPORT, insert-only. No recontabiliza ni toca nativos.
 *
 * Match:
 * 1) aplicped FAC → ref COM
 * 2) si no hay COM: FAC → PEP y hermanas COM que referencian el mismo PEP
 * 3) mapear COM (suc|nro) → recepcion_proveedor Anita
 */
final class ComprasCpRecepcionAnitaBackfillSupport
{
    /**
     * @return array{
     *   por_com: array<string, list<object>>,
     *   por_com_prov: array<string, list<object>>
     * }
     */
    public static function indexarRecepciones(): array
    {
        $porCom = [];
        $porComProv = [];

        $rows = DB::table('recepcion_proveedor')
            ->whereNotNull('anita_nro')
            ->where('anita_nro', '>', 0)
            ->get([
                'id',
                'empresa_id',
                'proveedor_id',
                'ordencompra_id',
                'anita_sucursal',
                'anita_nro',
                'anita_tipo',
                'anita_letra',
            ]);

        foreach ($rows as $r) {
            $clave = (int) $r->anita_sucursal.'|'.(int) $r->anita_nro;
            $porCom[$clave][] = $r;
            $provId = (int) ($r->proveedor_id ?? 0);
            if ($provId > 0) {
                $porComProv[$provId.'|'.$clave][] = $r;
            }
        }

        return [
            'por_com' => $porCom,
            'por_com_prov' => $porComProv,
        ];
    }

    /**
     * @param  array{por_com: array<string, list<object>>, por_com_prov: array<string, list<object>>}  $indiceRec
     * @return array{
     *   vincular: list<array{cp_id: int, recepcion_id: int, orden: int, via: string, com_clave: string}>,
     *   actualizar_modo: list<int>,
     *   stats: array<string, int|list<string>>,
     *   muestra: list<array<string, mixed>>
     * }
     */
    public static function planificar(
        string $desde,
        string $hasta,
        array $indiceRec,
        ?int $limite = null,
        ?MayorConceptoAnitaBridgeReader $reader = null,
    ): array {
        $reader ??= new MayorConceptoAnitaBridgeReader;
        $stats = [
            'candidatas' => 0,
            'vincular_cps' => 0,
            'vincular_links' => 0,
            'via_com' => 0,
            'via_pep' => 0,
            'sin_aplicped' => 0,
            'sin_com_en_aplicped' => 0,
            'com_faltante_erp' => 0,
            'ambiguo_oc' => 0,
            'ambiguo_recepcion' => 0,
            'sin_proveedor' => 0,
            'ya_con_pivot' => 0,
            'errores_bridge' => [],
        ];
        $vincular = [];
        $actualizarModo = [];
        $muestra = [];

        $query = DB::table('comprobante_proveedor as cp')
            ->join('tipotransaccion_compra as tc', 'tc.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'cp.proveedor_id')
            ->whereBetween('cp.fechacomprobante', [$desde, $hasta])
            ->where('cp.origen_entrada', 'ANITA_IMPORT')
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('comprobante_proveedor_recepcion as cpr')
                    ->whereColumn('cpr.comprobante_proveedor_id', 'cp.id');
            })
            ->orderBy('cp.id')
            ->select([
                'cp.id',
                'cp.empresa_id',
                'cp.proveedor_id',
                'cp.ordencompra_id',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
                'cp.modo_carga',
                'tc.abreviatura',
                'p.codigo as proveedor_codigo',
            ]);

        if ($limite !== null && $limite > 0) {
            $query->limit($limite);
        }

        $cps = $query->get();
        $stats['candidatas'] = $cps->count();

        $facturas = [];
        $cpsPorClave = [];
        foreach ($cps as $cp) {
            $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($cp->proveedor_codigo ?? ''));
            $tipo = strtoupper(trim((string) $cp->abreviatura));
            $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) $cp->letra);
            $suc = (int) $cp->sucursal;
            $nro = (int) $cp->numerocomprobante;
            if ($prov === '' || $tipo === '' || $nro <= 0) {
                $stats['sin_proveedor']++;

                continue;
            }
            $clave = self::claveFac($prov, $tipo, $letra, $suc, $nro);
            $facturas[] = [$prov, $tipo, $letra, $suc, $nro];
            $cpsPorClave[$clave][] = $cp;
        }

        $errores = [];
        $aplicpedRows = $reader->cargarAplicpedPorFacturas($facturas, $errores);
        $stats['errores_bridge'] = $errores;

        /** @var array<string, list<object>> $aplPorFac */
        $aplPorFac = [];
        $nrosPep = [];
        foreach ($aplicpedRows as $apl) {
            $clave = self::claveFacDesdeApl($apl);
            if ($clave === '') {
                continue;
            }
            $aplPorFac[$clave][] = $apl;
            if (strtoupper(trim((string) ($apl->aplp_ref_tipo ?? ''))) === 'PEP') {
                $nroPep = (int) ($apl->aplp_ref_nro ?? 0);
                if ($nroPep > 0) {
                    $nrosPep[$nroPep] = true;
                }
            }
        }

        $erroresPep = [];
        $hermanosPep = $reader->cargarAplicpedPorReferenciasTipo('PEP', array_keys($nrosPep), $erroresPep);
        if ($erroresPep !== []) {
            $stats['errores_bridge'] = array_merge($stats['errores_bridge'], $erroresPep);
        }

        /** @var array<string, list<object>> $comPorPep */
        $comPorPep = [];
        foreach ($hermanosPep as $hermano) {
            if (strtoupper(trim((string) ($hermano->aplp_tipo ?? ''))) !== 'COM') {
                continue;
            }
            $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($hermano->aplp_proveedor ?? ''));
            $refLetra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($hermano->aplp_ref_letra ?? 'X'));
            $refSuc = (int) ($hermano->aplp_ref_sucursal ?? 0);
            $refNro = (int) ($hermano->aplp_ref_nro ?? 0);
            if ($prov === '' || $refNro <= 0) {
                continue;
            }
            $clavePep = $prov.'|PEP|'.$refLetra.'|'.$refSuc.'|'.$refNro;
            $comPorPep[$clavePep][] = $hermano;
        }

        foreach ($cpsPorClave as $claveFac => $grupo) {
            foreach ($grupo as $cp) {
                $resultado = self::resolverCp(
                    $cp,
                    $aplPorFac[$claveFac] ?? [],
                    $comPorPep,
                    $indiceRec,
                );

                if ($resultado['stat'] !== 'ok') {
                    $stats[$resultado['stat']] = ((int) ($stats[$resultado['stat']] ?? 0)) + 1;

                    continue;
                }

                $stats['vincular_cps']++;
                if ($resultado['via'] === 'com') {
                    $stats['via_com']++;
                } else {
                    $stats['via_pep']++;
                }

                $orden = 1;
                foreach ($resultado['recepciones'] as $rec) {
                    $stats['vincular_links']++;
                    $vincular[] = [
                        'cp_id' => (int) $cp->id,
                        'recepcion_id' => (int) $rec['id'],
                        'orden' => $orden,
                        'via' => $resultado['via'],
                        'com_clave' => $rec['com_clave'],
                    ];
                    $orden++;
                }

                if ((string) ($cp->modo_carga ?? '') !== ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
                    $actualizarModo[] = (int) $cp->id;
                }

                if (count($muestra) < 15) {
                    $muestra[] = [
                        'cp_id' => (int) $cp->id,
                        'factura' => $claveFac,
                        'via' => $resultado['via'],
                        'coms' => count($resultado['recepciones']),
                        'rec_ids' => implode(',', array_map(static fn ($r) => $r['id'], $resultado['recepciones'])),
                    ];
                }
            }
        }

        return [
            'vincular' => $vincular,
            'actualizar_modo' => array_values(array_unique($actualizarModo)),
            'stats' => $stats,
            'muestra' => $muestra,
        ];
    }

    /**
     * @param  list<object>  $apls
     * @param  array<string, list<object>>  $comPorPep
     * @param  array{por_com: array<string, list<object>>, por_com_prov: array<string, list<object>>}  $indiceRec
     * @return array{
     *   stat: string,
     *   via?: string,
     *   recepciones?: list<array{id: int, com_clave: string, ordencompra_id: ?int}>
     * }
     */
    public static function resolverCp(
        object $cp,
        array $apls,
        array $comPorPep,
        array $indiceRec,
    ): array {
        if ($apls === []) {
            return ['stat' => 'sin_aplicped'];
        }

        $provCodigo = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita(
            (string) ($cp->proveedor_codigo ?? '')
        );
        $comsDirectas = [];
        $peps = [];

        foreach ($apls as $apl) {
            $refTipo = strtoupper(trim((string) ($apl->aplp_ref_tipo ?? '')));
            $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
            $refNro = (int) ($apl->aplp_ref_nro ?? 0);
            $refLetra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($apl->aplp_ref_letra ?? 'X'));
            if ($refNro <= 0) {
                continue;
            }
            if ($refTipo === 'COM') {
                $comsDirectas[$refSuc.'|'.$refNro] = [
                    'suc' => $refSuc,
                    'nro' => $refNro,
                    'letra' => $refLetra,
                ];
            } elseif ($refTipo === 'PEP') {
                $peps[$provCodigo.'|PEP|'.$refLetra.'|'.$refSuc.'|'.$refNro] = true;
            }
        }

        $via = 'com';
        $coms = $comsDirectas;

        if ($coms === [] && $peps !== []) {
            $via = 'pep';
            foreach (array_keys($peps) as $clavePep) {
                foreach ($comPorPep[$clavePep] ?? [] as $hermano) {
                    $suc = (int) ($hermano->aplp_sucursal ?? 0);
                    $nro = (int) ($hermano->aplp_nro ?? 0);
                    if ($nro <= 0) {
                        continue;
                    }
                    $coms[$suc.'|'.$nro] = [
                        'suc' => $suc,
                        'nro' => $nro,
                        'letra' => ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($hermano->aplp_letra ?? 'X')),
                    ];
                }
            }
        }

        if ($coms === []) {
            return ['stat' => 'sin_com_en_aplicped'];
        }

        $proveedorId = (int) ($cp->proveedor_id ?? 0);
        $empresaId = (int) ($cp->empresa_id ?? 0);
        $ocCp = (int) ($cp->ordencompra_id ?? 0);

        $recepciones = [];
        $ocsVistas = [];

        foreach ($coms as $comClave => $com) {
            $candidatas = self::candidatasRecepcion($comClave, $proveedorId, $empresaId, $indiceRec);
            if ($candidatas === null) {
                return ['stat' => 'ambiguo_recepcion'];
            }
            if ($candidatas === []) {
                return ['stat' => 'com_faltante_erp'];
            }
            $rec = $candidatas[0];
            $ocRec = (int) ($rec->ordencompra_id ?? 0);
            if ($ocRec > 0) {
                $ocsVistas[$ocRec] = true;
            }
            $recepciones[$comClave] = [
                'id' => (int) $rec->id,
                'com_clave' => $comClave,
                'ordencompra_id' => $ocRec > 0 ? $ocRec : null,
            ];
        }

        if ($ocCp > 0) {
            $filtradas = array_values(array_filter(
                $recepciones,
                static fn (array $r) => $r['ordencompra_id'] === null || (int) $r['ordencompra_id'] === $ocCp
            ));
            if ($filtradas === []) {
                return ['stat' => 'ambiguo_oc'];
            }
            $recepciones = [];
            foreach ($filtradas as $r) {
                $recepciones[$r['com_clave']] = $r;
            }
            $ocsVistas = [$ocCp => true];
        } elseif (count($ocsVistas) > 1) {
            return ['stat' => 'ambiguo_oc'];
        }

        return [
            'stat' => 'ok',
            'via' => $via,
            'recepciones' => array_values($recepciones),
        ];
    }

    /**
     * @param  array{por_com: array<string, list<object>>, por_com_prov: array<string, list<object>>}  $indiceRec
     * @return list<object>|null null = ambiguo
     */
    public static function candidatasRecepcion(
        string $comClave,
        int $proveedorId,
        int $empresaId,
        array $indiceRec,
    ): ?array {
        $pool = [];
        if ($proveedorId > 0 && isset($indiceRec['por_com_prov'][$proveedorId.'|'.$comClave])) {
            $pool = $indiceRec['por_com_prov'][$proveedorId.'|'.$comClave];
        } else {
            $pool = $indiceRec['por_com'][$comClave] ?? [];
        }

        if ($pool === []) {
            return [];
        }

        if ($empresaId > 0) {
            $mismaEmpresa = array_values(array_filter(
                $pool,
                static fn ($r) => (int) $r->empresa_id === $empresaId
            ));
            if ($mismaEmpresa !== []) {
                $pool = $mismaEmpresa;
            }
        }

        if (count($pool) === 1) {
            return $pool;
        }

        // Varias filas misma clave COM: ambiguo si no se pudo reducir a una.
        return null;
    }

    /**
     * @param  list<array{cp_id: int, recepcion_id: int, orden: int, via: string, com_clave: string}>  $vincular
     * @param  list<int>  $actualizarModo
     * @return array{links: int, modos: int}
     */
    public static function aplicar(array $vincular, array $actualizarModo, bool $actualizarModoCarga = true): array
    {
        $links = 0;
        $now = now();

        foreach (array_chunk($vincular, 500) as $lote) {
            $rows = [];
            foreach ($lote as $item) {
                $rows[] = [
                    'comprobante_proveedor_id' => $item['cp_id'],
                    'recepcion_proveedor_id' => $item['recepcion_id'],
                    'orden' => $item['orden'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            // Unique (cp, recepcion): ignore duplicates if re-run.
            $links += DB::table('comprobante_proveedor_recepcion')->insertOrIgnore($rows);
        }

        $modos = 0;
        if ($actualizarModoCarga && $actualizarModo !== []) {
            foreach (array_chunk($actualizarModo, 500) as $ids) {
                $modos += DB::table('comprobante_proveedor')
                    ->whereIn('id', $ids)
                    ->where('origen_entrada', 'ANITA_IMPORT')
                    ->where('modo_carga', '!=', ComprobanteProveedorModoCarga::ASIGNA_RECEPCION)
                    ->update(['modo_carga' => ComprobanteProveedorModoCarga::ASIGNA_RECEPCION]);
            }
        }

        return ['links' => $links, 'modos' => $modos];
    }

    public static function claveFac(string $prov, string $tipo, string $letra, int $suc, int $nro): string
    {
        return $prov.'|'.strtoupper(trim($tipo)).'|'
            .ComprobanteProveedorAnitaImportClaveSupport::letra($letra).'|'
            .$suc.'|'.$nro;
    }

    public static function claveFacDesdeApl(object $apl): string
    {
        $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($apl->aplp_proveedor ?? ''));
        $tipo = strtoupper(trim((string) ($apl->aplp_tipo ?? '')));
        $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($apl->aplp_letra ?? ''));
        $suc = (int) ($apl->aplp_sucursal ?? 0);
        $nro = (int) ($apl->aplp_nro ?? 0);
        if ($prov === '' || $tipo === '' || $nro <= 0) {
            return '';
        }

        return self::claveFac($prov, $tipo, $letra, $suc, $nro);
    }
}
