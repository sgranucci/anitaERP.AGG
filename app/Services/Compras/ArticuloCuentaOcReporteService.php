<?php

namespace App\Services\Compras;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\ArticuloCuentaOcAnitaBridgeReader;
use App\Support\Compras\ArticuloCuentaOcReporteFiltros;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Compras\RequisicionReporteCriteriosSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ArticuloCuentaOcReporteService
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private ArticuloCuentaOcAnitaBridgeReader $anitaReader,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array<string, int>,
     *     aviso_anita?: string|null
     * }
     */
    public function generar(array $filtros): array
    {
        $lineas = $this->leerLineasAnita($filtros);
        $enriquecidas = $this->enriquecerConErp($lineas, $filtros);
        $modo = (string) ($filtros['modo'] ?? ArticuloCuentaOcReporteFiltros::MODO_RESUMEN);

        $filas = $modo === ArticuloCuentaOcReporteFiltros::MODO_DETALLE
            ? $this->armarDetalle($enriquecidas)
            : $this->armarResumen($enriquecidas);

        if (! empty($filtros['solo_multi_proveedor'])) {
            $filas = $this->filtrarSoloMultiProveedor($filas, $modo, $enriquecidas);
        }

        if (! empty($filtros['sin_cuenta_erp'])) {
            $filas = array_values(array_filter(
                $filas,
                static fn (array $f) => empty($f['cuenta_codigo']),
            ));
        }

        if (! empty($filtros['solo_diferencia_cuenta'])) {
            $filas = array_values(array_filter(
                $filas,
                static fn (array $f) => ! empty($f['cuenta_diferencia']),
            ));
        }

        $cuentaFiltro = trim((string) ($filtros['cuenta_codigo'] ?? ''));
        if ($cuentaFiltro !== '') {
            $filas = array_values(array_filter(
                $filas,
                static function (array $f) use ($cuentaFiltro): bool {
                    $needle = mb_strtolower($cuentaFiltro);
                    $campos = [
                        (string) ($f['cuenta_codigo'] ?? ''),
                        (string) ($f['cuenta_nombre'] ?? ''),
                        (string) ($f['cuenta_anita_codigo'] ?? ''),
                        (string) ($f['cuenta_anita_nombre'] ?? ''),
                    ];
                    foreach ($campos as $campo) {
                        if ($campo !== '' && str_contains(mb_strtolower($campo), $needle)) {
                            return true;
                        }
                    }

                    return false;
                },
            ));
        }

        return [
            'filas' => $filas,
            'totales' => $this->totalesDesdeFilas($filas, $modo),
        ];
    }

    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));
        $total = count($filas);
        $offset = ($page - 1) * $perPage;

        return new LengthAwarePaginator(
            array_slice($filas, $offset, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>|null  $empresaQuery
     */
    public function subtituloFiltros(array $filtros, $empresaQuery = null): string
    {
        $partes = [];

        $ids = $filtros['empresa_ids'] ?? [];
        if ($ids !== [] && $empresaQuery !== null) {
            $nombres = collect($empresaQuery)
                ->whereIn('id', $ids)
                ->pluck('nombre')
                ->filter()
                ->values()
                ->all();
            if ($nombres !== []) {
                $txt = 'Empresas: '.implode(', ', $nombres);
                if (count($ids) > 1 && ! empty($filtros['consolidar_empresas'])) {
                    $txt .= ' (consolidado)';
                }
                $partes[] = $txt;
            }
        }

        $partes[] = 'Período: '.ArticuloCuentaOcReporteFiltros::formatearPeriodoTexto($filtros);
        $partes[] = 'Fuente OC: Anita (pendmovp + stkmae)';
        $partes[] = ArticuloCuentaOcReporteFiltros::etiquetaModo(
            (string) ($filtros['modo'] ?? ArticuloCuentaOcReporteFiltros::MODO_RESUMEN),
        );

        if (($filtros['sku'] ?? '') !== '') {
            $partes[] = 'SKU: '.$filtros['sku'];
        }
        if (($filtros['cuenta_codigo'] ?? '') !== '') {
            $partes[] = 'Cuenta: '.$filtros['cuenta_codigo'];
        }

        $subProv = OrdencompraReporteCriteriosSupport::subtituloProveedores($filtros);
        if ($subProv !== null) {
            $partes[] = $subProv;
        }

        if (! empty($filtros['solo_multi_proveedor'])) {
            $partes[] = 'Solo ítems con más de un proveedor';
        }
        if (! empty($filtros['sin_cuenta_erp'])) {
            $partes[] = 'Solo sin cuenta ERP';
        }
        if (! empty($filtros['solo_diferencia_cuenta'])) {
            $partes[] = 'Solo con diferencia ERP vs Anita';
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<object>
     */
    private function leerLineasAnita(array $filtros): array
    {
        $empresaIds = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            fn (int $id) => $id > 0,
        ));

        $fechaDesde = $this->fechaYmd($filtros['fecha_desde'] ?? null);
        $fechaHasta = $this->fechaYmd($filtros['fecha_hasta'] ?? null);
        if ($fechaDesde <= 0) {
            return [];
        }

        $codigosProveedorFiltro = $this->codigosProveedorAnita($filtros['proveedores'] ?? '');
        $skuFiltro = mb_strtolower(trim((string) ($filtros['sku'] ?? '')));

        $todas = [];
        $empresasAnitaHechas = [];

        foreach ($empresaIds as $empresaId) {
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
            if ($empresaAnita <= 0 || isset($empresasAnitaHechas[$empresaAnita])) {
                continue;
            }
            $empresasAnitaHechas[$empresaAnita] = true;

            $filas = $this->anitaReader->listarLineasOc($empresaAnita, $fechaDesde, $fechaHasta);
            foreach ($filas as $fila) {
                $fila->_empresa_id_erp = $empresaId;
                $fila->_empresa_anita = $empresaAnita;

                $skuAnita = $this->normalizarSku((string) ($fila->penvp_articulo ?? ''));
                if ($skuAnita === '') {
                    continue;
                }
                if ($skuFiltro !== '' && ! str_contains(mb_strtolower($skuAnita), $skuFiltro)) {
                    continue;
                }

                $provAnita = $this->normalizarCodigoProveedor((string) ($fila->penvp_proveedor ?? ''));
                if ($codigosProveedorFiltro !== [] && ! in_array($provAnita, $codigosProveedorFiltro, true)) {
                    // También aceptar pad 6 por si el filtro vino con ceros
                    $pad = str_pad($provAnita, 6, '0', STR_PAD_LEFT);
                    $match = false;
                    foreach ($codigosProveedorFiltro as $c) {
                        if ($c === $provAnita || str_pad($c, 6, '0', STR_PAD_LEFT) === $pad) {
                            $match = true;
                            break;
                        }
                    }
                    if (! $match) {
                        continue;
                    }
                }

                $todas[] = $fila;
            }
        }

        return $todas;
    }

    /**
     * @param  list<object>  $lineas
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function enriquecerConErp(array $lineas, array $filtros): array
    {
        if ($lineas === []) {
            return [];
        }

        $skus = [];
        $codigosProv = [];
        $empresaIds = [];
        $codigosCuentaAnita = [];
        foreach ($lineas as $linea) {
            $sku = $this->normalizarSku((string) ($linea->penvp_articulo ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
            $prov = $this->normalizarCodigoProveedor((string) ($linea->penvp_proveedor ?? ''));
            if ($prov !== '') {
                $codigosProv[$prov] = true;
            }
            $empresaIds[(int) ($linea->_empresa_id_erp ?? 0)] = true;
            $ctaAnita = $this->normalizarCodigoCuenta((string) ($linea->stkm_cta_contablec ?? ''));
            if ($ctaAnita !== '') {
                $codigosCuentaAnita[$ctaAnita] = true;
            }
        }

        $articulos = $this->mapArticulosPorSku(array_keys($skus));
        $proveedores = $this->mapProveedoresPorCodigo(array_keys($codigosProv));
        $empresas = $this->mapEmpresas(array_keys($empresaIds));
        $cuentasAnita = $this->mapCuentasPorCodigo(array_keys($codigosCuentaAnita));

        $out = [];
        foreach ($lineas as $linea) {
            $sku = $this->normalizarSku((string) ($linea->penvp_articulo ?? ''));
            $codigoProv = $this->normalizarCodigoProveedor((string) ($linea->penvp_proveedor ?? ''));
            $nroOc = (int) ($linea->penvp_nro ?? 0);
            $empresaId = (int) ($linea->_empresa_id_erp ?? 0);

            $art = $articulos[$sku] ?? null;
            $prov = $proveedores[$codigoProv]
                ?? $proveedores[ltrim($codigoProv, '0')]
                ?? null;

            $descAnita = trim((string) ($linea->penvp_desc ?? ''));
            if ($descAnita === '') {
                $descAnita = trim((string) ($linea->stkm_desc ?? ''));
            }
            $descripcion = $art['descripcion'] ?? ($descAnita !== '' ? $descAnita : $sku);

            $cuentaErp = (string) ($art['cuenta_codigo'] ?? '');
            $cuentaAnita = $this->normalizarCodigoCuenta((string) ($linea->stkm_cta_contablec ?? ''));
            // Conservar código Anita "tal cual" legible (sin forzar ltrim en pantalla si venía con formato)
            $cuentaAnitaMostrar = $this->codigoCuentaParaMostrar((string) ($linea->stkm_cta_contablec ?? ''));
            $cuentaAnitaInfo = $cuentasAnita[$cuentaAnita]
                ?? $cuentasAnita[$cuentaAnitaMostrar]
                ?? null;

            $out[] = [
                'sku' => $sku,
                'descripcion_articulo' => $descripcion,
                'articulo_id' => $art['id'] ?? null,
                'cuenta_codigo' => $cuentaErp,
                'cuenta_nombre' => $art['cuenta_nombre'] ?? '',
                'cuentacontable_id' => $art['cuentacontable_id'] ?? null,
                'cuenta_anita_codigo' => $cuentaAnitaMostrar,
                'cuenta_anita_nombre' => $cuentaAnitaInfo['nombre'] ?? '',
                'cuenta_anita_id' => $cuentaAnitaInfo['id'] ?? null,
                'cuenta_diferencia' => $this->cuentasDifieren($cuentaErp, $cuentaAnitaMostrar),
                'codigoproveedor' => $codigoProv,
                'nombreproveedor' => $prov['nombre'] ?? ('Prov. '.$codigoProv),
                'proveedor_id' => $prov['id'] ?? null,
                'numero_oc' => $nroOc > 0 ? $nroOc : null,
                'ref_oc' => $this->formatearRefOc($linea),
                'fecha_oc' => $this->fechaAnitaASql((string) ($linea->penvp_fecha ?? '')),
                'empresa_id' => $empresaId,
                'nombreempresa' => $empresas[$empresaId] ?? '',
                'cantidad' => (float) ($linea->penvp_cantidad ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function armarResumen(array $lineas): array
    {
        /** @var array<string, array<string, mixed>> $porSku */
        $porSku = [];

        foreach ($lineas as $linea) {
            $sku = (string) ($linea['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            if (! isset($porSku[$sku])) {
                $porSku[$sku] = [
                    'sku' => $sku,
                    'descripcion_articulo' => $linea['descripcion_articulo'] ?? '',
                    'articulo_id' => $linea['articulo_id'] ?? null,
                    'cuenta_codigo' => $linea['cuenta_codigo'] ?? '',
                    'cuenta_nombre' => $linea['cuenta_nombre'] ?? '',
                    'cuentacontable_id' => $linea['cuentacontable_id'] ?? null,
                    'cuenta_anita_codigo' => $linea['cuenta_anita_codigo'] ?? '',
                    'cuenta_anita_nombre' => $linea['cuenta_anita_nombre'] ?? '',
                    'cuenta_anita_id' => $linea['cuenta_anita_id'] ?? null,
                    'nombreempresa' => $linea['nombreempresa'] ?? '',
                    'empresa_id' => $linea['empresa_id'] ?? null,
                    '_proveedores' => [],
                    '_ocs' => [],
                    'total_lineas' => 0,
                ];
            }

            $porSku[$sku]['total_lineas']++;

            // Si Anita trae cuenta y aún no la tenemos, o si aparece una distinta, conservar la primera no vacía
            if (($porSku[$sku]['cuenta_anita_codigo'] ?? '') === ''
                && ($linea['cuenta_anita_codigo'] ?? '') !== ''
            ) {
                $porSku[$sku]['cuenta_anita_codigo'] = $linea['cuenta_anita_codigo'];
                $porSku[$sku]['cuenta_anita_nombre'] = $linea['cuenta_anita_nombre'] ?? '';
                $porSku[$sku]['cuenta_anita_id'] = $linea['cuenta_anita_id'] ?? null;
            }

            $codigoProv = (string) ($linea['codigoproveedor'] ?? '');
            if ($codigoProv !== '') {
                if (! isset($porSku[$sku]['_proveedores'][$codigoProv])) {
                    $porSku[$sku]['_proveedores'][$codigoProv] = [
                        'codigo' => $codigoProv,
                        'nombre' => $linea['nombreproveedor'] ?? '',
                        'proveedor_id' => $linea['proveedor_id'] ?? null,
                        'veces' => 0,
                        'ocs' => [],
                    ];
                }
                $porSku[$sku]['_proveedores'][$codigoProv]['veces']++;
                $nro = $linea['numero_oc'] ?? null;
                if ($nro) {
                    $porSku[$sku]['_proveedores'][$codigoProv]['ocs'][(int) $nro] = true;
                    $porSku[$sku]['_ocs'][(int) $nro] = true;
                }
            } elseif (! empty($linea['numero_oc'])) {
                $porSku[$sku]['_ocs'][(int) $linea['numero_oc']] = true;
            }

            // Preferir descripción ERP si aparece
            if (! empty($linea['articulo_id']) && empty($porSku[$sku]['articulo_id'])) {
                $porSku[$sku]['articulo_id'] = $linea['articulo_id'];
                $porSku[$sku]['descripcion_articulo'] = $linea['descripcion_articulo'] ?? $porSku[$sku]['descripcion_articulo'];
                $porSku[$sku]['cuenta_codigo'] = $linea['cuenta_codigo'] ?? '';
                $porSku[$sku]['cuenta_nombre'] = $linea['cuenta_nombre'] ?? '';
                $porSku[$sku]['cuentacontable_id'] = $linea['cuentacontable_id'] ?? null;
            }
        }

        $filas = [];
        foreach ($porSku as $grupo) {
            $proveedores = array_values($grupo['_proveedores']);
            usort($proveedores, static fn ($a, $b) => ($b['veces'] <=> $a['veces']) ?: strcmp($a['codigo'], $b['codigo']));

            $textosProv = [];
            foreach ($proveedores as $p) {
                $ocsProv = array_keys($p['ocs']);
                sort($ocsProv, SORT_NUMERIC);
                $textosProv[] = trim($p['codigo'].' '.$p['nombre']).' ('.$p['veces'].')';
            }

            $ocs = array_keys($grupo['_ocs']);
            sort($ocs, SORT_NUMERIC);

            $ctaErp = (string) ($grupo['cuenta_codigo'] ?? '');
            $ctaAnita = (string) ($grupo['cuenta_anita_codigo'] ?? '');
            $diferencia = $this->cuentasDifieren($ctaErp, $ctaAnita);

            $filas[] = [
                'tipo_fila' => 'resumen',
                'sku' => $grupo['sku'],
                'descripcion_articulo' => $grupo['descripcion_articulo'],
                'articulo_id' => $grupo['articulo_id'],
                'cuenta_codigo' => $ctaErp,
                'cuenta_nombre' => $grupo['cuenta_nombre'],
                'cuentacontable_id' => $grupo['cuentacontable_id'],
                'cuenta_texto' => $this->textoCuenta($ctaErp, (string) ($grupo['cuenta_nombre'] ?? '')),
                'cuenta_anita_codigo' => $ctaAnita,
                'cuenta_anita_nombre' => $grupo['cuenta_anita_nombre'] ?? '',
                'cuenta_anita_id' => $grupo['cuenta_anita_id'] ?? null,
                'cuenta_anita_texto' => $this->textoCuenta($ctaAnita, (string) ($grupo['cuenta_anita_nombre'] ?? '')),
                'cuenta_diferencia' => $diferencia,
                'cuenta_coincide_texto' => $diferencia ? 'No' : 'Sí',
                'proveedores_texto' => implode('; ', $textosProv),
                'cantidad_proveedores' => count($proveedores),
                'refs_oc' => implode(', ', $ocs),
                'cantidad_oc' => count($ocs),
                'total_lineas' => (int) $grupo['total_lineas'],
                'nombreempresa' => $grupo['nombreempresa'],
                'empresa_id' => $grupo['empresa_id'],
                'codigoproveedor' => '',
                'nombreproveedor' => '',
                'proveedor_id' => null,
                'veces' => (int) $grupo['total_lineas'],
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            $cmpCuenta = strcmp((string) ($a['cuenta_codigo'] ?? ''), (string) ($b['cuenta_codigo'] ?? ''));
            if ($cmpCuenta !== 0) {
                return $cmpCuenta;
            }

            return strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
        });

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function armarDetalle(array $lineas): array
    {
        /** @var array<string, array<string, mixed>> $porClave */
        $porClave = [];

        foreach ($lineas as $linea) {
            $sku = (string) ($linea['sku'] ?? '');
            $codigoProv = (string) ($linea['codigoproveedor'] ?? '');
            if ($sku === '') {
                continue;
            }
            $clave = $sku.'|'.$codigoProv;
            if (! isset($porClave[$clave])) {
                $porClave[$clave] = [
                    'sku' => $sku,
                    'descripcion_articulo' => $linea['descripcion_articulo'] ?? '',
                    'articulo_id' => $linea['articulo_id'] ?? null,
                    'cuenta_codigo' => $linea['cuenta_codigo'] ?? '',
                    'cuenta_nombre' => $linea['cuenta_nombre'] ?? '',
                    'cuentacontable_id' => $linea['cuentacontable_id'] ?? null,
                    'cuenta_anita_codigo' => $linea['cuenta_anita_codigo'] ?? '',
                    'cuenta_anita_nombre' => $linea['cuenta_anita_nombre'] ?? '',
                    'cuenta_anita_id' => $linea['cuenta_anita_id'] ?? null,
                    'codigoproveedor' => $codigoProv,
                    'nombreproveedor' => $linea['nombreproveedor'] ?? '',
                    'proveedor_id' => $linea['proveedor_id'] ?? null,
                    'nombreempresa' => $linea['nombreempresa'] ?? '',
                    'empresa_id' => $linea['empresa_id'] ?? null,
                    '_ocs' => [],
                    'total_lineas' => 0,
                ];
            }
            $porClave[$clave]['total_lineas']++;
            if (($porClave[$clave]['cuenta_anita_codigo'] ?? '') === ''
                && ($linea['cuenta_anita_codigo'] ?? '') !== ''
            ) {
                $porClave[$clave]['cuenta_anita_codigo'] = $linea['cuenta_anita_codigo'];
                $porClave[$clave]['cuenta_anita_nombre'] = $linea['cuenta_anita_nombre'] ?? '';
                $porClave[$clave]['cuenta_anita_id'] = $linea['cuenta_anita_id'] ?? null;
            }
            if (! empty($linea['numero_oc'])) {
                $porClave[$clave]['_ocs'][(int) $linea['numero_oc']] = true;
            }
            if (! empty($linea['articulo_id']) && empty($porClave[$clave]['articulo_id'])) {
                $porClave[$clave]['articulo_id'] = $linea['articulo_id'];
                $porClave[$clave]['descripcion_articulo'] = $linea['descripcion_articulo'] ?? $porClave[$clave]['descripcion_articulo'];
                $porClave[$clave]['cuenta_codigo'] = $linea['cuenta_codigo'] ?? '';
                $porClave[$clave]['cuenta_nombre'] = $linea['cuenta_nombre'] ?? '';
                $porClave[$clave]['cuentacontable_id'] = $linea['cuentacontable_id'] ?? null;
            }
        }

        $filas = [];
        foreach ($porClave as $grupo) {
            $ocs = array_keys($grupo['_ocs']);
            sort($ocs, SORT_NUMERIC);
            $ctaErp = (string) ($grupo['cuenta_codigo'] ?? '');
            $ctaAnita = (string) ($grupo['cuenta_anita_codigo'] ?? '');
            $diferencia = $this->cuentasDifieren($ctaErp, $ctaAnita);
            $filas[] = [
                'tipo_fila' => 'detalle',
                'sku' => $grupo['sku'],
                'descripcion_articulo' => $grupo['descripcion_articulo'],
                'articulo_id' => $grupo['articulo_id'],
                'cuenta_codigo' => $ctaErp,
                'cuenta_nombre' => $grupo['cuenta_nombre'],
                'cuentacontable_id' => $grupo['cuentacontable_id'],
                'cuenta_texto' => $this->textoCuenta($ctaErp, (string) ($grupo['cuenta_nombre'] ?? '')),
                'cuenta_anita_codigo' => $ctaAnita,
                'cuenta_anita_nombre' => $grupo['cuenta_anita_nombre'] ?? '',
                'cuenta_anita_id' => $grupo['cuenta_anita_id'] ?? null,
                'cuenta_anita_texto' => $this->textoCuenta($ctaAnita, (string) ($grupo['cuenta_anita_nombre'] ?? '')),
                'cuenta_diferencia' => $diferencia,
                'cuenta_coincide_texto' => $diferencia ? 'No' : 'Sí',
                'codigoproveedor' => $grupo['codigoproveedor'],
                'nombreproveedor' => $grupo['nombreproveedor'],
                'proveedor_id' => $grupo['proveedor_id'],
                'proveedores_texto' => trim($grupo['codigoproveedor'].' '.$grupo['nombreproveedor']),
                'cantidad_proveedores' => 1,
                'veces' => (int) $grupo['total_lineas'],
                'refs_oc' => implode(', ', $ocs),
                'cantidad_oc' => count($ocs),
                'total_lineas' => (int) $grupo['total_lineas'],
                'nombreempresa' => $grupo['nombreempresa'],
                'empresa_id' => $grupo['empresa_id'],
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            $cmpSku = strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
            if ($cmpSku !== 0) {
                return $cmpSku;
            }

            return ($b['veces'] <=> $a['veces'])
                ?: strcmp((string) ($a['codigoproveedor'] ?? ''), (string) ($b['codigoproveedor'] ?? ''));
        });

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function filtrarSoloMultiProveedor(array $filas, string $modo, array $lineas): array
    {
        $skusMulti = [];
        $porSku = [];
        foreach ($lineas as $linea) {
            $sku = (string) ($linea['sku'] ?? '');
            $prov = (string) ($linea['codigoproveedor'] ?? '');
            if ($sku === '' || $prov === '') {
                continue;
            }
            $porSku[$sku][$prov] = true;
        }
        foreach ($porSku as $sku => $provs) {
            if (count($provs) > 1) {
                $skusMulti[$sku] = true;
            }
        }

        return array_values(array_filter(
            $filas,
            static fn (array $f) => isset($skusMulti[(string) ($f['sku'] ?? '')]),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, int>
     */
    private function totalesDesdeFilas(array $filas, string $modo): array
    {
        $skus = [];
        $provs = [];
        $ocs = 0;
        $conDiferencia = 0;
        foreach ($filas as $fila) {
            $sku = (string) ($fila['sku'] ?? '');
            if ($sku !== '') {
                $skus[$sku] = true;
            }
            if ($modo === ArticuloCuentaOcReporteFiltros::MODO_DETALLE) {
                $p = (string) ($fila['codigoproveedor'] ?? '');
                if ($p !== '') {
                    $provs[$p] = true;
                }
            } else {
                $ocs += (int) ($fila['cantidad_oc'] ?? 0);
            }
            if ($modo === ArticuloCuentaOcReporteFiltros::MODO_DETALLE) {
                $ocs += (int) ($fila['cantidad_oc'] ?? 0);
            }
            if (! empty($fila['cuenta_diferencia'])) {
                $conDiferencia++;
            }
        }

        return [
            'total_articulos' => count($skus),
            'total_filas' => count($filas),
            'total_proveedores' => $modo === ArticuloCuentaOcReporteFiltros::MODO_DETALLE
                ? count($provs)
                : 0,
            'total_oc_refs' => $ocs,
            'con_diferencia_cuenta' => $conDiferencia,
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array<string, mixed>>
     */
    private function mapArticulosPorSku(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $rows = DB::table('articulo as a')
            ->leftJoin('cuentacontable as cta', 'cta.id', '=', 'a.cuentacontablecompra_id')
            ->whereIn('a.sku', $skus)
            ->select([
                'a.id',
                'a.sku',
                'a.descripcion',
                'a.cuentacontablecompra_id',
                'cta.codigo as cuenta_codigo',
                'cta.nombre as cuenta_nombre',
            ])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $skuNorm = $this->normalizarSku((string) $row->sku);
            $map[$skuNorm] = [
                'id' => (int) $row->id,
                'descripcion' => (string) ($row->descripcion ?? ''),
                'cuentacontable_id' => $row->cuentacontablecompra_id
                    ? (int) $row->cuentacontablecompra_id
                    : null,
                'cuenta_codigo' => trim((string) ($row->cuenta_codigo ?? '')),
                'cuenta_nombre' => trim((string) ($row->cuenta_nombre ?? '')),
            ];
            // También indexar por sku tal cual por si no hubo ltrim
            $map[(string) $row->sku] = $map[$skuNorm];
        }

        // Variantes con ceros a la izquierda que puedan existir en ERP
        $faltantes = [];
        foreach ($skus as $sku) {
            if (! isset($map[$sku])) {
                $faltantes[] = $sku;
            }
        }
        if ($faltantes !== []) {
            // Buscar por ltrim en PHP sobre un lote más amplio no es ideal; reintento LIKE no.
            // Intentar match numérico: SKUs ERP sin ceros.
            $todos = DB::table('articulo as a')
                ->leftJoin('cuentacontable as cta', 'cta.id', '=', 'a.cuentacontablecompra_id')
                ->whereIn('a.sku', array_map(
                    static fn ($s) => ltrim((string) $s, '0') ?: '0',
                    $faltantes,
                ))
                ->select([
                    'a.id',
                    'a.sku',
                    'a.descripcion',
                    'a.cuentacontablecompra_id',
                    'cta.codigo as cuenta_codigo',
                    'cta.nombre as cuenta_nombre',
                ])
                ->get();
            foreach ($todos as $row) {
                $skuNorm = $this->normalizarSku((string) $row->sku);
                $payload = [
                    'id' => (int) $row->id,
                    'descripcion' => (string) ($row->descripcion ?? ''),
                    'cuentacontable_id' => $row->cuentacontablecompra_id
                        ? (int) $row->cuentacontablecompra_id
                        : null,
                    'cuenta_codigo' => trim((string) ($row->cuenta_codigo ?? '')),
                    'cuenta_nombre' => trim((string) ($row->cuenta_nombre ?? '')),
                ];
                $map[$skuNorm] = $payload;
                $map[(string) $row->sku] = $payload;
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $codigos
     * @return array<string, array{id: int, nombre: string}>
     */
    private function mapProveedoresPorCodigo(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        $variantes = [];
        foreach ($codigos as $c) {
            $variantes[$c] = true;
            $variantes[ltrim($c, '0') ?: '0'] = true;
            $variantes[str_pad(ltrim($c, '0') ?: '0', 6, '0', STR_PAD_LEFT)] = true;
        }

        $rows = DB::table('proveedor')
            ->whereIn('codigo', array_keys($variantes))
            ->select(['id', 'codigo', 'nombre'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $payload = [
                'id' => (int) $row->id,
                'nombre' => (string) ($row->nombre ?? ''),
            ];
            $cod = (string) $row->codigo;
            $map[$cod] = $payload;
            $map[$this->normalizarCodigoProveedor($cod)] = $payload;
            $map[ltrim($cod, '0') ?: '0'] = $payload;
        }

        return $map;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function mapEmpresas(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0));
        if ($ids === []) {
            return [];
        }

        return DB::table('empresa')
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->map(fn ($n) => (string) $n)
            ->all();
    }

    /**
     * @param  list<string>  $codigos
     * @return array<string, array{id: int, nombre: string}>
     */
    private function mapCuentasPorCodigo(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        $variantes = [];
        foreach ($codigos as $c) {
            $c = trim((string) $c);
            if ($c === '') {
                continue;
            }
            $variantes[$c] = true;
            $norm = $this->normalizarCodigoCuenta($c);
            if ($norm !== '') {
                $variantes[$norm] = true;
            }
        }

        if ($variantes === []) {
            return [];
        }

        $rows = DB::table('cuentacontable')
            ->whereIn('codigo', array_keys($variantes))
            ->select(['id', 'codigo', 'nombre'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $payload = [
                'id' => (int) $row->id,
                'nombre' => (string) ($row->nombre ?? ''),
            ];
            $cod = trim((string) $row->codigo);
            $map[$cod] = $payload;
            $norm = $this->normalizarCodigoCuenta($cod);
            if ($norm !== '') {
                $map[$norm] = $payload;
            }
        }

        return $map;
    }

    private function textoCuenta(string $codigo, string $nombre): string
    {
        $codigo = trim($codigo);
        $nombre = trim($nombre);
        if ($codigo === '' && $nombre === '') {
            return '(sin cuenta)';
        }
        if ($nombre === '') {
            return $codigo;
        }
        if ($codigo === '') {
            return $nombre;
        }

        return $codigo.' — '.$nombre;
    }

    private function cuentasDifieren(string $cuentaErp, string $cuentaAnita): bool
    {
        $a = $this->normalizarCodigoCuenta($cuentaErp);
        $b = $this->normalizarCodigoCuenta($cuentaAnita);

        // Si ambas vacías, no hay diferencia útil que marcar
        if ($a === '' && $b === '') {
            return false;
        }

        return $a !== $b;
    }

    private function normalizarCodigoCuenta(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $codigo === '0') {
            return '';
        }
        if (ctype_digit($codigo)) {
            return ltrim($codigo, '0') ?: '0';
        }

        return $codigo;
    }

    private function codigoCuentaParaMostrar(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $codigo === '0') {
            return '';
        }

        return $codigo;
    }

    private function formatearRefOc(object $linea): string
    {
        $nro = (int) ($linea->penvp_nro ?? 0);
        if ($nro <= 0) {
            return '';
        }
        $tipo = trim((string) ($linea->penvp_tipo ?? 'PEP'));
        $letra = trim((string) ($linea->penvp_letra ?? 'X'));
        $suc = (int) ($linea->penvp_sucursal ?? 0);

        return $tipo.'/'.$letra.'/'.$suc.'/'.$nro;
    }

    private function normalizarSku(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }
        // Conservar SKUs alfanuméricos; solo ltrim ceros si es numérico puro
        if (ctype_digit($sku)) {
            return ltrim($sku, '0') ?: '0';
        }

        return $sku;
    }

    private function normalizarCodigoProveedor(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        if (ctype_digit($codigo)) {
            return ltrim($codigo, '0') ?: '0';
        }

        return $codigo;
    }

    /**
     * @return list<string>
     */
    private function codigosProveedorAnita(string $proveedores): array
    {
        $lista = RequisicionReporteCriteriosSupport::parseListaCodigos($proveedores);
        if ($lista === []) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (string $c) => $this->normalizarCodigoProveedor($c),
            $lista,
        )));
    }

    private function fechaYmd(?string $fecha): int
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return 0;
        }

        try {
            return (int) Carbon::parse($fecha)->format('Ymd');
        } catch (\Throwable) {
            return (int) str_replace('-', '', substr($fecha, 0, 10));
        }
    }

    private function fechaAnitaASql(string $ymd): ?string
    {
        $ymd = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($ymd) !== 8) {
            return null;
        }

        return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
    }
}
