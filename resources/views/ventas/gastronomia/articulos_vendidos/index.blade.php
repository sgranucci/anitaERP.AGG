@extends("theme.$theme.layout")

@section('titulo')
    Artículos vendidos gastronomía
@endsection

@section("scripts")
@include('ventas.gastronomia.facturas_dia.partials.estilos_acciones_tabla')
<style>
    .gastro-facturas-articulo-wrap {
        max-height: 420px;
        overflow: auto;
    }
    .gastro-facturas-articulo-wrap table {
        margin-bottom: 0;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    .gastro-facturas-articulo-wrap th {
        position: sticky;
        top: 0;
        background: #f8f9fa;
        z-index: 2;
    }
</style>
<script>
    window.ARTICULOS_VENDIDOS_GASTRONOMIA = {
        urlApiFacturasBase: @json(url('ventas/gastronomia/articulos-vendidos/api')),
        urlFacturaVerBase: @json(($puede_ver_factura ?? false) ? url('ventas/gastronomia/facturas-dia') : null),
        urlResolverFormulaBase: @json(($puede_ver_formula ?? false) ? url('stock/formula-articulo/resolver-por-articulo') : null),
        urlFormulaBase: @json(($puede_ver_formula ?? false) ? url('stock/formula-articulo') : null),
        puedeVerFactura: @json($puede_ver_factura ?? false),
        puedeVerFormula: @json($puede_ver_formula ?? false),
        puedeVerMovimientos: @json($puede_ver_movimientos ?? false),
        filtrosQuery: @json($filtrosQuery ?? []),
    };
</script>
<script src="{{ asset('assets/pages/scripts/includes/formula_articulo_accion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/formula_articulo_accion.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/articulos_vendidos_filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/articulos_vendidos_filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/articulos_vendidos.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/articulos_vendidos.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

<?php use App\Support\Ventas\GastronomiaArticulosVendidosListadoFiltros; ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($jornada['jornada_abierta']))
            <div class="alert alert-info py-2 mb-2">
                Jornada activa:
                <strong>{{ $jornada['fecha_jornada_fmt'] ?? $jornada['fecha_jornada'] }}</strong>
                @if (($filtros['fecha_desde'] ?? '') === ($jornada['fecha_jornada'] ?? '') && ($filtros['fecha_hasta'] ?? '') === ($jornada['fecha_jornada'] ?? ''))
                    · filtro por defecto
                @endif
            </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Artículos vendidos gastronomía</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-articulos-vendidos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => GastronomiaArticulosVendidosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('gastronomia_articulos_vendidos'),
                        'placeholder' => 'Búsqueda rápida (SKU, descripción, PV…)',
                        'toggleTarget' => '#panel-filtros-articulos-vendidos',
                        'toggleId' => 'btn-toggle-filtros-articulos-vendidos',
                        'inputId' => 'filtro_valor',
                    ])
                    <a href="{{ route('gastronomia_facturas_dia', array_filter([
                        'fecha' => $filtros['fecha_desde'] ?? null,
                        'todas_pc' => '1',
                    ])) }}"
                       class="btn btn-outline-secondary btn-sm ml-1"
                       title="Ver facturas del día con los mismos filtros de jornada">
                        <i class="fa fa-file-text-o"></i> Facturas del día
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('gastronomia_articulos_vendidos') }}" id="form-filtros-articulos-vendidos" class="mb-0">
                @include('ventas.gastronomia.articulos_vendidos.partials.filtros_listado')
            </form>
            <div class="card-body p-0">
                @php $tot = $totales ?? []; @endphp
                <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <div class="mb-1 mb-md-0">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_gastronomia_articulos_vendidos',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                    <div class="small mb-1 mb-md-0 text-md-right">
                        <span class="text-muted">Totales filtro:</span>
                        <strong>{{ (int) ($tot['cantidad_articulos'] ?? 0) }}</strong> filas
                        · Cant.
                        <strong>{{ number_format((float) ($tot['cantidad_total'] ?? 0), 3, ',', '.') }}</strong>
                        · Importe
                        <strong>${{ number_format((float) ($tot['importe_total'] ?? 0), 2, ',', '.') }}</strong>
                        ·
                        <strong>{{ (int) ($tot['cantidad_comprobantes'] ?? 0) }}</strong> comprob.
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Descripción</th>
                                <th>Punto de venta</th>
                                <th>Depósito ítem</th>
                                <th>Depósito insumos</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Importe</th>
                                <th class="text-right" title="Comprobantes distintos">Comprob.</th>
                                <th class="width120" data-orderable="false"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $f)
                                <tr data-articulo-id="{{ $f->articulo_id }}"
                                    data-deposito-id="{{ $f->deposito_id ?? 0 }}"
                                    data-puntoventa-id="{{ $f->puntoventa_id ?? 0 }}">
                                    <td>
                                        @include('ventas.gastronomia.facturas_dia.partials.link_sku_articulo', [
                                            'sku' => $f->sku,
                                            'articuloId' => $f->articulo_id,
                                        ])
                                    </td>
                                    <td><small>{{ $f->descripcion ?? '—' }}</small></td>
                                    <td><small>{{ $f->puntoventa_etiqueta !== '' ? $f->puntoventa_etiqueta : '—' }}</small></td>
                                    <td><small>{{ ($f->deposito_venta_etiqueta ?? '') !== '' ? $f->deposito_venta_etiqueta : '—' }}</small></td>
                                    <td><small>{{ ($f->deposito_insumos_etiqueta ?? '') !== '' ? $f->deposito_insumos_etiqueta : '—' }}</small></td>
                                    <td class="text-right"><small>{{ number_format((float) ($f->cantidad_total ?? 0), 3, ',', '.') }}</small></td>
                                    <td class="text-right"><small>${{ number_format((float) ($f->importe_total ?? 0), 2, ',', '.') }}</small></td>
                                    <td class="text-right"><small>{{ (int) ($f->cantidad_comprobantes ?? 0) }}</small></td>
                                    <td class="facturas-dia-tabla-acciones text-nowrap">
                                        @if ($puede_ver_articulo ?? false)
                                            <a href="{{ route('editar_articulo', ['id' => $f->articulo_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                               target="_blank"
                                               rel="noopener"
                                               class="btn-accion-tabla tooltipsC"
                                               title="Consultar artículo">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if ($puede_ver_formula ?? false)
                                            @include('includes.btn_formula_articulo', ['articuloId' => $f->articulo_id])
                                        @endif
                                        <button type="button"
                                                class="btn-accion-tabla tooltipsC js-av-ver-facturas"
                                                data-articulo-id="{{ $f->articulo_id }}"
                                                data-sku="{{ $f->sku ?? '' }}"
                                                data-deposito-id="{{ $f->deposito_id ?? 0 }}"
                                                data-puntoventa-id="{{ $f->puntoventa_id ?? 0 }}"
                                                data-cantidad-total="{{ $f->cantidad_total ?? 0 }}"
                                                title="Ver comprobantes que incluyen este artículo">
                                            <i class="fas fa-file-invoice text-primary"></i>
                                        </button>
                                        @if ($puede_ver_movimientos ?? false)
                                            <button type="button"
                                                    class="btn-accion-tabla tooltipsC js-av-ver-movimientos"
                                                    data-articulo-id="{{ $f->articulo_id }}"
                                                    data-sku="{{ $f->sku ?? '' }}"
                                                    data-deposito-id="{{ $f->deposito_id ?? 0 }}"
                                                    data-puntoventa-id="{{ $f->puntoventa_id ?? 0 }}"
                                                    data-cantidad-total="{{ $f->cantidad_total ?? 0 }}"
                                                    title="Ver movimientos de stock que componen la cantidad del renglón">
                                                <i class="fa fa-exchange text-primary"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        Sin ventas de gastronomía para los filtros indicados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if (method_exists($filas, 'hasPages') && $filas->hasPages())
                    <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-2 border-top bg-light">
                        <small class="text-muted mb-2 mb-md-0">
                            Mostrando {{ $filas->firstItem() ?? 0 }}–{{ $filas->lastItem() ?? 0 }}
                            de {{ $filas->total() }} fila(s)
                        </small>
                        <div>{{ $filas->onEachSide(1)->links() }}</div>
                    </div>
                @elseif (method_exists($filas, 'total'))
                    <div class="px-3 py-2 border-top bg-light">
                        <small class="text-muted">{{ $filas->total() }} fila(s).</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-av-movimientos-articulo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modal-av-movimientos-titulo">Movimientos de stock del artículo</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div id="modal-av-movimientos-error" class="alert alert-danger py-2 small d-none" role="alert"></div>
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <p class="small text-muted mb-1 mb-md-0" id="modal-av-movimientos-subtitulo"></p>
                    <div class="d-flex flex-wrap align-items-center">
                        @if ($puede_ver_factura ?? false)
                            <button type="button"
                                    id="modal-av-link-ver-facturas"
                                    class="btn btn-outline-primary btn-sm d-none mr-1"
                                    title="Listar comprobantes del artículo con los mismos filtros">
                                <i class="fas fa-file-invoice"></i> Ver comprobantes
                            </button>
                        @endif
                        <a href="#" id="modal-av-link-kardex" class="btn btn-outline-secondary btn-sm d-none" target="_blank" rel="noopener">
                            <i class="fa fa-external-link-alt"></i> Abrir kardex completo
                        </a>
                    </div>
                </div>
                <div class="gastro-facturas-articulo-wrap">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Venta ID</th>
                                <th>Comprobante</th>
                                <th>Punto venta</th>
                                <th>Depósito</th>
                                <th class="text-right">Entrada</th>
                                <th class="text-right">Salida</th>
                                <th class="width120" data-orderable="false"></th>
                            </tr>
                        </thead>
                        <tbody id="modal-av-movimientos-body">
                            <tr><td colspan="9" class="text-muted small">Seleccione un artículo…</td></tr>
                        </tbody>
                        <tfoot id="modal-av-movimientos-foot" class="d-none">
                            <tr class="font-weight-bold bg-light">
                                <td colspan="7" class="text-right small">Totales movimientos / cant. renglón:</td>
                                <td class="text-right small" id="modal-av-mov-total-entrada">—</td>
                                <td class="text-right small" id="modal-av-mov-total-salida">—</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="small text-muted mb-0 mt-2" id="modal-av-movimientos-nota-cantidad"></p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-av-facturas-articulo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modal-av-facturas-titulo">Comprobantes del artículo</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div id="modal-av-facturas-error" class="alert alert-danger py-2 small d-none" role="alert"></div>
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <p class="small text-muted mb-1 mb-md-0" id="modal-av-facturas-subtitulo"></p>
                    <div class="d-flex flex-wrap align-items-center">
                        <a href="#" id="modal-av-link-formula" class="btn btn-outline-info btn-sm d-none mr-1" target="_blank" rel="noopener">
                            <i class="fa fa-flask"></i> Ver fórmula
                        </a>
                        <a href="#" id="modal-av-link-facturas-dia" class="btn btn-outline-secondary btn-sm d-none" target="_blank" rel="noopener">
                            <i class="fa fa-external-link-alt"></i> Abrir en facturas del día
                        </a>
                    </div>
                </div>
                <div class="gastro-facturas-articulo-wrap">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Venta ID</th>
                                <th>Comprobante</th>
                                <th>Jornada</th>
                                <th>Fecha</th>
                                <th>Punto venta</th>
                                <th>Depósito</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Importe</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="modal-av-facturas-body">
                            <tr><td colspan="9" class="text-muted small">Seleccione un artículo…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection
