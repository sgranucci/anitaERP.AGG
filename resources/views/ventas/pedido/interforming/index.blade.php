@extends("theme.$theme.layout")
@section('titulo')
Pedidos Interforming
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/interforming/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/interforming/index.js') }}" type="text/javascript"></script>
@endsection

<?php use App\Support\Ventas\PedidoInterformingListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Pedidos de clientes (Interforming)</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-pedido-interforming',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PedidoInterformingListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('pedido'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-pedido-interforming',
                        'toggleId' => 'btn-toggle-filtros-pedido-interforming',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_pedido', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-pedidos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('pedido') }}" id="form-filtros-pedido-interforming" class="mb-0">
                @include('ventas.pedido.interforming.partials.filtros_listado', [
                    'limpiarUrl' => route('pedido'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_pedido',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>C&oacute;digo</th>
                            <th>Fecha</th>
                            <th>Entrega</th>
                            <th>Cliente</th>
                            <th>O. Compra</th>
                            <th>Estado</th>
                            <th>Vendedor</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $pedido)
                            <tr>
                                <td>{{ $pedido->id }}</td>
                                <td>{{ $pedido->codigo }}</td>
                                <td>{{ optional($pedido->fecha)->format('d/m/Y') ?? substr((string) $pedido->fecha, 0, 10) }}</td>
                                <td>{{ optional($pedido->fechaentrega)->format('d/m/Y') ?? substr((string) $pedido->fechaentrega, 0, 10) }}</td>
                                <td>{{ $pedido->clientes->codigo ?? '' }} — {{ $pedido->clientes->nombre ?? '' }}</td>
                                <td>{{ $pedido->orden_compra }}</td>
                                <td>{{ $pedido->etiquetaEstado() }}</td>
                                <td>{{ $pedido->vendedores->nombre ?? '' }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('listar_pedido_pdf', $pedido->id) }}"
                                       class="btn-accion-tabla tooltipsC" title="PDF" target="_blank" rel="noopener">
                                        <i class="fa fa-file-pdf text-danger"></i>
                                    </a>
                                    @if (can('editar-pedidos', false))
                                        <a href="{{ route('editar_pedido', ['id' => $pedido->id] + $retornoListadoQuery) }}"
                                           class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-pedidos', false))
                                        <form action="{{ route('eliminar_pedido', $pedido->id) }}" method="POST" class="d-inline form-eliminar">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">Sin pedidos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@endsection
