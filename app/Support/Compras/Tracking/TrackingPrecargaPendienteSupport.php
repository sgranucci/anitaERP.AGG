<?php

namespace App\Support\Compras\Tracking;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\Tracking\TrackingIndiceSyncService;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Compras\Tracking\TrackingPdfReferencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Precargas con PDF aún no convertidas en comprobante ERP (pendientes de CxP).
 *
 * Misma regla que la bandeja de legajo: tienen archivo, no están anuladas /
 * cargadas en Anita / retenidas por entrega, y no existe un CP no anulado
 * que las cubra (por FK de precarga o por clave fiscal + empresa).
 */
final class TrackingPrecargaPendienteSupport
{
    /**
     * @return Builder<Precarga_Comprobante_Proveedor>
     */
    public static function consultaBase(EmpresaRepositoryInterface $empresaRepository): Builder
    {
        $query = Precarga_Comprobante_Proveedor::query()
            ->from('precarga_comprobante_proveedor as pcp')
            ->select([
                'pcp.id as id',
                'pcp.empresa_id as empresa_id',
                'pcp.proveedor_id as proveedor_id',
                'pcp.tipotransaccion_compra_id as tipotransaccion_compra_id',
                'pcp.letra as letra',
                'pcp.sucursal as sucursal',
                'pcp.numerocomprobante as numerocomprobante',
                'pcp.fechafactura as fechacomprobante',
                'pcp.fechavencimiento as fechavencimiento',
                'pcp.total as total',
                'pcp.estado as estado_precarga',
                'pcp.anita_nro_interno as anita_nro_interno',
                'pcp.numeroordencompra as numeroordencompra',
                'pcp.rutaalmacenamiento as rutaalmacenamiento',
                'empresa.nombre as nombreempresa',
                'proveedor.nombre as nombreproveedor',
                'proveedor.codigo as codigoproveedor',
                'proveedor.nroinscripcion as cuitproveedor',
                'tipotransaccion_compra.nombre as nombretipotransaccion_compra',
                'tipotransaccion_compra.abreviatura as abreviaturatipotransaccion_compra',
                'tipotransaccion_compra.codigoafip as codigoafiptipotransaccion_compra',
                DB::raw('COALESCE(pcp.fecharecepcionemail, pcp.created_at) as fechacarga_efectiva'),
                DB::raw("'".TrackingIndiceSyncService::FECHACARGA_PRECARGA."' as fechacarga_origen"),
                DB::raw('1 as pdf_disponible'),
                DB::raw("'".TrackingPdfReferencia::ORIGEN_PRECARGA."' as pdf_origen"),
                DB::raw('pcp.rutaalmacenamiento as pdf_ruta'),
                DB::raw('pcp.updated_at as sincronizado_at'),
                DB::raw("'' as pago_estado"),
                DB::raw('NULL as pago_saldo'),
                DB::raw('NULL as pago_fecha'),
                DB::raw('NULL as pago_op_referencia'),
                DB::raw('0 as pago_op_cantidad'),
                DB::raw('0 as pago_op_id'),
                DB::raw('NULL as fechacontabilizacion'),
                DB::raw('0 as numeroasiento'),
                DB::raw('NULL as asiento_id'),
                DB::raw("'PRECARGA_PENDIENTE' as estado"),
                DB::raw('1 as es_precarga'),
                // Se completa después de paginar (ver hidratarOrdencompraIds):
                // un JOIN/subquery contra ordencompra sin índice compuesto
                // reventaba el listado de Biyemas (~20k OC).
                DB::raw('NULL as ordencompra_id'),
            ])
            ->join('empresa', 'empresa.id', '=', 'pcp.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'pcp.proveedor_id')
            ->leftJoin(
                'tipotransaccion_compra',
                'tipotransaccion_compra.id',
                '=',
                'pcp.tipotransaccion_compra_id'
            );

        self::aplicarAlcancePendiente($query);
        $empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'pcp.empresa_id');

        return $query;
    }

    /**
     * Resuelve ordencompra_id de un lote de precargas (empresa + número OC).
     *
     * @param  iterable<mixed>  $filas
     */
    public static function hidratarOrdencompraIds(iterable $filas): void
    {
        $filas = $filas instanceof \Illuminate\Support\Collection
            ? $filas
            : collect($filas);

        if ($filas->isEmpty()) {
            return;
        }

        $claves = [];
        foreach ($filas as $fila) {
            $empresaId = (int) ($fila->empresa_id ?? 0);
            $numero = trim((string) ($fila->numeroordencompra ?? ''));
            if ($empresaId <= 0 || $numero === '' || $numero === '0') {
                continue;
            }
            $claves[$empresaId.'|'.$numero] = [$empresaId, $numero];
        }

        if ($claves === []) {
            return;
        }

        $mapa = [];
        $query = DB::table('ordencompra')->select(['id', 'empresa_id', 'numeroordencompra']);
        $query->where(function ($q) use ($claves) {
            foreach ($claves as [$empresaId, $numero]) {
                $q->orWhere(function ($w) use ($empresaId, $numero) {
                    $w->where('empresa_id', $empresaId)
                        ->where('numeroordencompra', $numero);
                });
            }
        });

        foreach ($query->get() as $oc) {
            $clave = ((int) $oc->empresa_id).'|'.trim((string) $oc->numeroordencompra);
            // Primera coincidencia gana (equivalente al LIMIT 1 del SQL viejo).
            $mapa[$clave] ??= (int) $oc->id;
        }

        foreach ($filas as $fila) {
            $empresaId = (int) ($fila->empresa_id ?? 0);
            $numero = trim((string) ($fila->numeroordencompra ?? ''));
            $fila->ordencompra_id = $mapa[$empresaId.'|'.$numero] ?? null;
        }
    }

    /**
     * Conteo de precargas pendientes sin joins de grilla.
     *
     * `consultaBase` une empresa/proveedor/tipo/OC para pintar filas; en un
     * COUNT eso multiplica trabajo (sobre todo el left join a ordencompra) y
     * el chip del resumen se siente lento. Acá solo se joinea lo que el
     * filtro externo pide (familia → tipo; búsqueda → empresa/proveedor).
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function contarPendientes(
        EmpresaRepositoryInterface $empresaRepository,
        array $filtros
    ): int {
        $query = Precarga_Comprobante_Proveedor::query()
            ->from('precarga_comprobante_proveedor as pcp');

        self::aplicarAlcancePendiente($query);
        $empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'pcp.empresa_id');

        $familia = strtoupper(trim((string) ($filtros['familia'] ?? '')));
        $necesitaTipo = TrackingComprobanteFamilia::esFamiliaValida($familia);
        $valor = trim((string) ($filtros['valor'] ?? ($filtros['busqueda'] ?? '')));
        $necesitaEmpresaProveedor = $valor !== '';

        if ($necesitaTipo) {
            $query->leftJoin(
                'tipotransaccion_compra',
                'tipotransaccion_compra.id',
                '=',
                'pcp.tipotransaccion_compra_id'
            );
        }
        if ($necesitaEmpresaProveedor) {
            $query->join('empresa', 'empresa.id', '=', 'pcp.empresa_id')
                ->leftJoin('proveedor', 'proveedor.id', '=', 'pcp.proveedor_id');
        }

        self::aplicarFiltros($query, $filtros);

        return (int) $query->reorder()->count('pcp.id');
    }

    /**
     * Totales del segmento precargas pendientes (COUNT + SUM) sin joins de grilla.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{registros: int, total: float}
     */
    public static function resumenPendientes(
        EmpresaRepositoryInterface $empresaRepository,
        array $filtros
    ): array {
        $query = Precarga_Comprobante_Proveedor::query()
            ->from('precarga_comprobante_proveedor as pcp');

        self::aplicarAlcancePendiente($query);
        $empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'pcp.empresa_id');

        $familia = strtoupper(trim((string) ($filtros['familia'] ?? '')));
        $necesitaTipo = TrackingComprobanteFamilia::esFamiliaValida($familia);
        $valor = trim((string) ($filtros['valor'] ?? ($filtros['busqueda'] ?? '')));
        $necesitaEmpresaProveedor = $valor !== '';

        if ($necesitaTipo) {
            $query->leftJoin(
                'tipotransaccion_compra',
                'tipotransaccion_compra.id',
                '=',
                'pcp.tipotransaccion_compra_id'
            );
        }
        if ($necesitaEmpresaProveedor) {
            $query->join('empresa', 'empresa.id', '=', 'pcp.empresa_id')
                ->leftJoin('proveedor', 'proveedor.id', '=', 'pcp.proveedor_id');
        }

        self::aplicarFiltros($query, $filtros);

        $fila = $query->reorder()
            ->selectRaw('count(pcp.id) as registros')
            ->selectRaw('coalesce(sum(pcp.total), 0) as total')
            ->first();

        return [
            'registros' => (int) ($fila->registros ?? 0),
            'total' => (float) ($fila->total ?? 0),
        ];
    }

    /**
     * @param  Builder<Precarga_Comprobante_Proveedor>  $query
     */
    public static function aplicarAlcancePendiente(Builder $query): void
    {
        $query->whereNotNull('pcp.rutaalmacenamiento')
            ->where('pcp.rutaalmacenamiento', '!=', '')
            ->where(function ($w) {
                $w->whereNull('pcp.estado')
                    ->orWhereRaw(
                        'UPPER(TRIM(pcp.estado)) NOT IN (?, ?, ?)',
                        [
                            PrecargaComprobanteEstados::ANULADA,
                            PrecargaComprobanteEstados::CARGADA_ANITA,
                            PrecargaComprobanteEstados::PENDIENTE_ENTREGA,
                        ]
                    );
            })
            // Dos NOT EXISTS (en vez de un OR): el OR adentro impide usar índices
            // y hace full scan de comprobante_proveedor por cada precarga (~4–5 s).
            ->whereNotExists(function ($cp) {
                $cp->selectRaw('1')
                    ->from('comprobante_proveedor as cp')
                    ->whereColumn('cp.precarga_comprobante_proveedor_id', 'pcp.id')
                    ->where(function ($w) {
                        $w->whereNull('cp.estado')
                            ->orWhereRaw('UPPER(TRIM(cp.estado)) != ?', ['ANULADA']);
                    });
            })
            ->whereNotExists(function ($cp) {
                $cp->selectRaw('1')
                    ->from('comprobante_proveedor as cp')
                    ->whereColumn('cp.empresa_id', 'pcp.empresa_id')
                    ->whereColumn('cp.letra', 'pcp.letra')
                    ->whereColumn('cp.sucursal', 'pcp.sucursal')
                    ->whereColumn('cp.numerocomprobante', 'pcp.numerocomprobante')
                    ->where(function ($w) {
                        $w->whereNull('cp.estado')
                            ->orWhereRaw('UPPER(TRIM(cp.estado)) != ?', ['ANULADA']);
                    });
            });
    }

    /**
     * Filtros externos del tracking (sin segmento de comprobantes).
     *
     * @param  Builder<Precarga_Comprobante_Proveedor>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarFiltros(Builder $query, array $filtros): void
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0 && ($filtros['empresa_scope'] ?? 'una') !== 'todas') {
            $query->where('pcp.empresa_id', (int) $filtros['empresa_id']);
        }
        if ((int) ($filtros['proveedor_id'] ?? 0) > 0) {
            $query->where('pcp.proveedor_id', (int) $filtros['proveedor_id']);
        }

        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        $eje = (string) ($filtros['eje_fecha'] ?? TrackingFacturasListadoFiltros::EJE_FECHA_COMPROBANTE);
        $columnaFecha = $eje === TrackingFacturasListadoFiltros::EJE_FECHA_CARGA
            ? 'COALESCE(pcp.fecharecepcionemail, pcp.created_at)'
            : 'pcp.fechafactura';

        if ($desde !== '') {
            $query->whereDate(\Illuminate\Support\Facades\DB::raw($columnaFecha), '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate(\Illuminate\Support\Facades\DB::raw($columnaFecha), '<=', $hasta);
        }

        $familia = strtoupper(trim((string) ($filtros['familia'] ?? '')));
        if (TrackingComprobanteFamilia::esFamiliaValida($familia)) {
            $codigos = TrackingComprobanteFamilia::codigosAfipDeFamilia($familia);
            $abreviaturas = TrackingComprobanteFamilia::abreviaturasDeFamilia($familia);
            $query->where(function ($q) use ($codigos, $abreviaturas) {
                if ($abreviaturas !== []) {
                    $q->whereIn('tipotransaccion_compra.abreviatura', $abreviaturas);
                }
                if ($codigos !== []) {
                    $q->orWhereIn('tipotransaccion_compra.codigoafip', $codigos);
                }
            });
        }

        $valor = trim((string) ($filtros['valor'] ?? ($filtros['busqueda'] ?? '')));
        if ($valor === '') {
            return;
        }

        $like = '%'.$valor.'%';
        $entero = filter_var($valor, FILTER_VALIDATE_INT);
        $query->where(function ($q) use ($valor, $like, $entero) {
            if ($entero !== false) {
                $q->orWhere('pcp.id', (int) $entero)
                    ->orWhere('pcp.sucursal', (int) $entero)
                    ->orWhere('pcp.numerocomprobante', (int) $entero)
                    ->orWhere('pcp.anita_nro_interno', (int) $entero);
            }
            $q->orWhere('empresa.nombre', 'like', $like)
                ->orWhere('proveedor.nombre', 'like', $like)
                ->orWhere('proveedor.nroinscripcion', 'like', $like)
                ->orWhere('pcp.letra', 'like', $like)
                ->orWhere('pcp.numeroordencompra', 'like', $like);
        });
    }
}
