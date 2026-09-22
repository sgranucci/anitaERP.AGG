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
                'ordencompra.id as ordencompra_id',
            ])
            ->join('empresa', 'empresa.id', '=', 'pcp.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'pcp.proveedor_id')
            ->leftJoin(
                'tipotransaccion_compra',
                'tipotransaccion_compra.id',
                '=',
                'pcp.tipotransaccion_compra_id'
            )
            ->leftJoin('ordencompra', function ($join) {
                $join->on('ordencompra.empresa_id', '=', 'pcp.empresa_id')
                    ->on('ordencompra.numeroordencompra', '=', 'pcp.numeroordencompra');
            });

        self::aplicarAlcancePendiente($query);
        $empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'pcp.empresa_id');

        return $query;
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
            ->whereNotExists(function ($cp) {
                $cp->selectRaw('1')
                    ->from('comprobante_proveedor as cp')
                    ->where(function ($w) {
                        $w->whereColumn('cp.precarga_comprobante_proveedor_id', 'pcp.id')
                            ->orWhere(function ($m) {
                                $m->whereColumn('cp.empresa_id', 'pcp.empresa_id')
                                    ->whereColumn('cp.letra', 'pcp.letra')
                                    ->whereColumn('cp.sucursal', 'pcp.sucursal')
                                    ->whereColumn('cp.numerocomprobante', 'pcp.numerocomprobante');
                            });
                    })
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
