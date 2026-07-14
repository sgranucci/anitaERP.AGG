@extends("theme.$theme.layout")
@section('titulo')
Pedidos de Clientes
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/salida.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/configurar_salida.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/proceso-overlay.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/imprimir.js') }}" type="text/javascript"></script>

<script>
window.seteoSalidaPrograma = @json(\App\Support\Configuracion\SeteoSalidaProgramaSupport::VENTAS_PEDIDO);

function eliminarPedido(event) {
  var opcion = confirm("Desea eliminar el pedido?");
  if(!opcion) {
    event.preventDefault();
  }
}
</script>
@endsection

<?php use App\Support\Ventas\PedidoListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Pedidos de clientes</h3>
                @include('includes.configurar-salida')
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-pedido',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PedidoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('pedido'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-pedido',
                        'toggleId' => 'btn-toggle-filtros-pedido',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_pedido', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-pedidos',
                    ])
                    @if (can('cierre-de-pedidos', false))
                        <a href="{{ route('cerrar_pedido') }}" class="btn btn-danger btn-sm ml-1">
                            <i class="fa fa-fw fa-times-circle"></i> Cierre de pedidos
                        </a>
                    @endif
                    <a href="#" onclick="return configurarSalida();" class="btn btn-outline-secondary btn-sm ml-1">
                        <i class="fa fa-fw fa-cog"></i> Configura salida
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('pedido') }}" id="form-filtros-pedido" class="mb-0">
                @include('ventas.partials.filtros_reparto_fecha_entrega')
                @include('ventas.pedido.partials.filtros_listado', [
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
                            <th>Fecha</th>
                            <th>Fecha entrega</th>
                            <th class="width50">Cliente</th>
                            <th>Cajas</th>
                            <th>Piezas</th>
                            <th>Kilos</th>
                            <th>Pesada</th>
                            <th>Reparto</th>
                            <th class="width60">Estado</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pedidos as $pedido)
                            <tr data-entry-id="{{ $pedido['id'] }}">
                                <td>{{ $pedido['id'] ?? '' }}</td>
                                <td>{{ date('d-m-Y', strtotime($pedido['fecha'] ?? '')) }}</td>
                                <td>{{ date('d-m-Y', strtotime($pedido['fechaentrega'] ?? '')) }}</td>
                                <td><b>{{ $pedido['nombrecliente'] ?? '' }}</b></td>
                                <td>
                                    @php $caja = 0; @endphp
                                    @foreach ($pedido->pedido_articulos as $item)
                                        @php $caja = $caja + $item->caja @endphp
                                    @endforeach
                                    {{ $caja }}
                                </td>
                                <td>
                                    @php $pieza = 0; @endphp
                                    @foreach ($pedido->pedido_articulos as $item)
                                        @php $pieza = $pieza + $item->pieza @endphp
                                    @endforeach
                                    {{ $pieza }}
                                </td>
                                <td>
                                    @php $kilo = 0; @endphp
                                    @foreach ($pedido->pedido_articulos as $item)
                                        @php $kilo = $kilo + $item->kilo @endphp
                                    @endforeach
                                    {{ $kilo }}
                                </td>
                                <td>
                                    @php $pesada = 0; @endphp
                                    @foreach ($pedido->pedido_articulos as $item)
                                        @php $pesada = $pesada + $item->pesada @endphp
                                    @endforeach
                                    {{ $pesada }}
                                </td>
                                <td>{{ $pedido->nombretransporte ?? '' }}</td>
                                <td>{{ $pedido['estado'] }}</td>
                                <td>
                                    @if (can('editar-pedidos', false))
                                        <a href="{{ route('editar_pedido', ['id' => $pedido['id']] + $retornoListadoQuery) }}"
                                           class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-pedidos', false) && $pedido['estado'] == 'Pendiente')
                                        <form action="{{ route('eliminar_pedido', ['id' => $pedido['id']]) }}"
                                              class="d-inline form-eliminar" method="POST">
                                            @csrf @method('delete')
                                            <button type="submit" onclick="eliminarPedido(event)"
                                                    class="btn-accion-tabla eliminar tooltipsC"
                                                    title="Eliminar este registro">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if (can('listar-pedidos', false))
                                        <a href="#"
                                           class="btn-accion-tabla tooltipsC btn-imprimir-pedido-listado"
                                           title="Imprimir pedido"
                                           data-pedido-id="{{ $pedido['id'] }}">
                                            <i class="fa fa-print"></i>
                                        </a>
                                        <a href="{{ route('listar_pedido_pdf', ['id' => $pedido['id']]) }}"
                                           class="btn-accion-tabla tooltipsC" title="Listar el pedido en PDF">
                                            <i class="fas fa-file-pdf text-danger"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center">Sin pedidos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $pedidos->appends($filtrosQuery ?? [])->links() }}

@include('includes.proceso-overlay-pedido')

@endsection
