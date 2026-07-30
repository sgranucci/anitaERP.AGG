@extends("theme.$theme.layout")
@section('titulo')
Movimientos de Stock
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/revertir.js') }}" type="text/javascript"></script>
@endsection

<?php use App\Support\Stock\MovimientoStockFerliSupport;
use App\Support\Stock\MovimientoStockListadoFiltros; ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Movimientos de Stock</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.stock.boton-manual-recepcion-movstock')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-movimientostock',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => MovimientoStockListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => route('movimientostock', MovimientoStockListadoFiltros::paraQueryStringEmpresa($filtros ?? [])),
                        'placeholder' => 'Búsqueda rápida (movimientos y transferencias)…',
                        'toggleTarget' => '#panel-filtros-movimientostock',
                        'toggleId' => 'btn-toggle-filtros-movimientostock',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_movimientostock'),
                        'nuevoRegistroCan' => 'crear-movimientos-de-stock',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('movimientostock') }}" id="form-filtros-movimientostock" class="mb-0">
                @include('stock.movimientostock.partials.filtros_listado')
            </form>
            @include('stock.movimientostock.partials.filtros_externos')
            @if(!empty($alcance_centro_costo))
                <div class="px-3 py-2 border-bottom bg-white text-muted small">
                    <i class="fa fa-filter"></i>
                    Listado limitado (movimientos de usuarios de su centro de costo): <strong>{{ $alcance_centro_costo }}</strong>
                </div>
            @endif
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_movimientostock',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
                            <th>Tipo de transacci&oacute;n</th>
                            <th>N&uacute;mero</th>
                            @if (MovimientoStockFerliSupport::esCalzadosFerli())
                                <th>Marca</th>
                            @endif
                            <th>Lote</th>
                            <th title="Transferencia: dep&oacute;sito o bien de origen. Movimiento: no aplica.">Dep. origen</th>
                            <th title="Transferencia: dep&oacute;sito o bien destino. Movimiento: dep&oacute;sito (c&oacute;digo &mdash; nombre).">Dep. destino</th>
                            <th>Empresa</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-center">&Iacute;tems</th>
                            <th>Estado</th>
                            <th class="width120" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @include('stock.movimientostock.partials.tabla_datos', [
                            'datas' => $datas,
                            'estado_enum' => $estado_enum,
                        ])
                    </tbody>
                </table>
            </div>
            @if (method_exists($datas, 'links'))
                <div class="card-footer clearfix">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
