<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\Venta;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportCacheReader;
use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportCacheSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteCodigoSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Sustituye líneas ficticias PF100026 / "import Anita" por renglones stkmov del cache local.
 */
final class GastronomiaImportAnitaReexpandirEmisionService
{
    public function __construct(
        private readonly GastronomiaAnitaImportCacheSupport $cacheSupport,
        private readonly GastronomiaFacturaImportacionAnitaService $importacion,
    ) {}

    /**
     * @param  list<string>  $codigosDescuento
     * @return array{
     *   candidatas:int,
     *   reexpandidas:int,
     *   omitidas:int,
     *   sin_stkmov_cache:int,
     *   errores:list<string>,
     *   renglones_creados:int,
     *   cache_directorio:string
     * }
     */
    public function reexpandir(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        array $codigosDescuento,
        string $sufijoCache = 'desc40legacy',
        bool $dryRun = false,
        int $limite = 0,
    ): array {
        $codigos = GastronomiaDescuentoReporteCodigoSupport::expandir(implode(',', $codigosDescuento));
        if ($codigos === []) {
            throw new \InvalidArgumentException('Indique al menos un código de descuento.');
        }

        $reader = $this->cacheSupport->crearReader($empresaId, $fechaDesde, $fechaHasta, $sufijoCache);
        $manifest = $this->cacheSupport->leerManifest($empresaId, $fechaDesde, $fechaHasta, $sufijoCache);
        $directorio = (string) ($manifest['directorio'] ?? '');

        $ret = [
            'candidatas' => 0,
            'reexpandidas' => 0,
            'omitidas' => 0,
            'sin_stkmov_cache' => 0,
            'errores' => [],
            'renglones_creados' => 0,
            'cache_directorio' => $directorio,
        ];

        $candidatas = $this->listarCandidatas($empresaId, $fechaDesde, $fechaHasta, $codigos, $limite);
        $ret['candidatas'] = $candidatas->count();

        foreach ($candidatas as $row) {
            $ventaId = (int) $row->venta_id;
            $sucursal = (int) preg_replace('/\D+/', '', (string) $row->puntoventa_codigo);
            $nro = (int) $row->numerocomprobante;
            $tipo = strtoupper(trim((string) $row->tipo_comprobante));
            $etiqueta = "FAC B {$sucursal}-{$nro} venta_id={$ventaId}";

            $lineasStk = $this->lineasStkmovUtilizablesDesdeCache(
                $reader,
                $sucursal,
                $nro,
                $tipo !== '' ? $tipo : 'FAC',
            );

            if ($lineasStk === []) {
                $ret['sin_stkmov_cache']++;
                $ret['errores'][] = $etiqueta.': sin stkmov en cache local';

                continue;
            }

            if ($dryRun) {
                $ret['reexpandidas']++;

                continue;
            }

            try {
                $venta = Venta::query()->findOrFail($ventaId);
                $timestamp = Carbon::parse((string) ($venta->created_at ?? now()));
                $monedaId = (int) ($venta->moneda_id ?? 1) ?: 1;
                $renglones = $this->importacion->regenerarEmisionesDesdeStkmov(
                    $ventaId,
                    $lineasStk,
                    $monedaId,
                    $timestamp,
                );
                $ret['reexpandidas']++;
                $ret['renglones_creados'] += $renglones;
            } catch (\Throwable $e) {
                $ret['errores'][] = $etiqueta.': '.$e->getMessage();
            }
        }

        $ret['omitidas'] = max(0, $ret['candidatas'] - $ret['reexpandidas'] - count($ret['errores']) + $ret['sin_stkmov_cache']);

        return $ret;
    }

    /**
     * @param  list<string>  $codigosDescuento
     * @return array{candidatas:int,cubiertas_cache:int,faltantes_cache:int}
     */
    public function verificarCoberturaCache(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        array $codigosDescuento,
        string $sufijoCache = 'desc40legacy',
    ): array {
        $codigos = GastronomiaDescuentoReporteCodigoSupport::expandir(implode(',', $codigosDescuento));
        $reader = $this->cacheSupport->crearReader($empresaId, $fechaDesde, $fechaHasta, $sufijoCache);
        $candidatas = $this->listarCandidatas($empresaId, $fechaDesde, $fechaHasta, $codigos, 0);

        $cubiertas = 0;
        $faltantes = 0;
        foreach ($candidatas as $row) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) $row->puntoventa_codigo);
            $nro = (int) $row->numerocomprobante;
            $tipo = strtoupper(trim((string) $row->tipo_comprobante));
            $lineas = $this->lineasStkmovUtilizablesDesdeCache(
                $reader,
                $sucursal,
                $nro,
                $tipo !== '' ? $tipo : 'FAC',
            );
            if ($lineas !== []) {
                $cubiertas++;
            } else {
                $faltantes++;
            }
        }

        return [
            'candidatas' => $candidatas->count(),
            'cubiertas_cache' => $cubiertas,
            'faltantes_cache' => $faltantes,
        ];
    }

    /**
     * @param  list<string>  $codigosDescuento
     * @return Collection<int, object{
     *   venta_id:int,
     *   puntoventa_codigo:string,
     *   numerocomprobante:int,
     *   tipo_comprobante:string
     * }>
     */
    private function listarCandidatas(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        array $codigosDescuento,
        int $limite,
    ): Collection {
        $query = DB::table('venta as v')
            ->join('venta_emision as ve', 've.venta_id', '=', 'v.id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('cuenta_gastronomia as cg', 'cg.id', '=', 'vge.cuenta_gastronomia_id')
            ->leftJoin('descuento_gastronomia as dg', 'dg.id', '=', 'cg.descuento_gastronomia_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->whereNull('v.deleted_at')
            ->where('pv.empresa_id', $empresaId)
            ->where('cg.origen_pos', 'import_anita')
            ->whereBetween('v.fechajornada', [$fechaDesde, $fechaHasta])
            ->where(function ($q): void {
                $q->where('ve.detalle', 'like', '%import Anita%');
            })
            ->where(function ($q) use ($codigosDescuento): void {
                if ($codigosDescuento !== []) {
                    $q->whereIn('dg.codigo', $codigosDescuento);
                }
                $q->orWhere(function ($q2): void {
                    $q2->whereNull('cg.descuento_gastronomia_id')
                        ->where('ve.detalle', 'like', '%Cortes%import Anita%');
                });
            })
            ->select([
                'v.id as venta_id',
                'pv.codigo as puntoventa_codigo',
                'v.numerocomprobante',
                'tt.abreviatura as tipo_comprobante',
            ])
            ->distinct()
            ->orderBy('v.id');

        if ($limite > 0) {
            $query->limit($limite);
        }

        return $query->get();
    }

    /**
     * @return list<stdClass>
     */
    private function lineasStkmovUtilizablesDesdeCache(
        GastronomiaAnitaImportCacheReader $reader,
        int $sucursal,
        int $nro,
        string $tipoComprobante,
    ): array {
        $tipos = GastronomiaAnitaImportEmpresaSupport::tiposDetalleAnita($tipoComprobante);
        $lineas = $this->filtrarLineasStkmovUtilizables($reader->stkmov($sucursal, $nro, $tipos));
        if ($lineas !== []) {
            return $lineas;
        }

        // Rebisco legacy: detalle en stkmov como NCD con misma sucursal/número que cabecera FAC.
        if (! in_array('NCD', $tipos, true)) {
            $lineas = $this->filtrarLineasStkmovUtilizables($reader->stkmov($sucursal, $nro, ['NCD']));
        }

        return $lineas;
    }

    /**
     * @param  list<object|stdClass>  $lineas
     * @return list<stdClass>
     */
    private function filtrarLineasStkmovUtilizables(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $stk) {
            $skuRaw = trim((string) ($stk->stkv_articulo ?? ''));
            if ($skuRaw === '' || $skuRaw === 'texto') {
                continue;
            }
            $out[] = $stk instanceof stdClass ? $stk : (object) $stk;
        }

        return $out;
    }
}
