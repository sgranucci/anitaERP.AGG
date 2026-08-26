@extends("theme.$theme.layout")
@section('titulo')
Remitos de Clientes
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/remito/filtro.js') }}" type="text/javascript"></script>
@if (\App\Services\Ventas\RemitoImportarDesdeAnitaService::esElBierzo() && can('ejecutar-importar-remito-anita', false))
<script src="{{ asset('assets/pages/scripts/ventas/remito/importar_anita_index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/remito/importar_anita_index.js')) ?: time() }}" type="text/javascript"></script>
@endif
<script>
function eliminarRemito(event) {
  if(!confirm("Desea eliminar el remito?")) {
    event.preventDefault();
  }
}
</script>
@endsection

<?php use App\Support\Ventas\RemitoListadoFiltros; ?>

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
                <h3 class="card-title">Remitos de clientes</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-remito',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RemitoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('remito'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-remito',
                        'toggleId' => 'btn-toggle-filtros-remito',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_remito', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-remitos',
                    ])
                    @if (\App\Services\Ventas\RemitoImportarDesdeAnitaService::esElBierzo() && can('ejecutar-importar-remito-anita', false))
                        <button type="button"
                                class="btn btn-outline-light btn-sm ml-1"
                                data-toggle="modal"
                                data-target="#modalImportarRemitoAnita"
                                title="Importar remitos Anita REM R 1 por fecha y repartos">
                            <i class="fa fa-fw fa-download"></i> Importar Anita
                        </button>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('remito') }}" id="form-filtros-remito" class="mb-0">
                    @include('ventas.partials.filtros_reparto_fecha_entrega')
                    <div class="border-bottom">
                        <div class="card-body bg-white py-2 text-body">
                            <div class="form-row align-items-center">
                                <div class="form-group col-md-auto mb-0">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox"
                                               class="custom-control-input"
                                               name="solo_huerfanos"
                                               id="solo_huerfanos"
                                               value="1"
                                               form="form-filtros-remito"
                                               {{ !empty($filtros['solo_huerfanos']) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="solo_huerfanos">
                                            Solo huérfanos (sin factura)
                                        </label>
                                    </div>
                                </div>
                                @if (can('listar-asignacion-remito-factura', false))
                                    <div class="form-group col-md-auto mb-0 ml-auto">
                                        <a href="{{ route('asignacion_remito_factura') }}" class="btn btn-outline-primary btn-sm">
                                            <i class="fa fa-link"></i> Asignar a facturas
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @include('ventas.remito.partials.filtros_listado', [
                    'limpiarUrl' => route('remito'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_remito',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Fecha entrega</th>
                            <th class="width50">Cliente</th>
                            <th>Cajas</th>
                            <th>Piezas</th>
                            <th>Kilos</th>
                            <th>Reparto</th>
                            <th class="width60">Estado</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($remitos as $remito)
                            <tr data-entry-id="{{ $remito['id'] }}">
                                <td>{{ $remito['id'] ?? '' }}</td>
                                <td>{{ $remito['codigo'] ?? '' }}</td>
                                <td>{{ date('d-m-Y', strtotime($remito['fecha'] ?? '')) }}</td>
                                <td>{{ date('d-m-Y', strtotime($remito['fechaentrega'] ?? '')) }}</td>
                                <td><b>{{ $remito['nombrecliente'] ?? '' }}</b></td>
                                <td>
                                    @php $caja = 0; @endphp
                                    @foreach ($remito->remito_articulos as $item)
                                        @php $caja += $item->caja @endphp
                                    @endforeach
                                    {{ $caja }}
                                </td>
                                <td>
                                    @php $pieza = 0; @endphp
                                    @foreach ($remito->remito_articulos as $item)
                                        @php $pieza += $item->pieza @endphp
                                    @endforeach
                                    {{ $pieza }}
                                </td>
                                <td>
                                    @php $kilo = 0; @endphp
                                    @foreach ($remito->remito_articulos as $item)
                                        @php $kilo += $item->kilo @endphp
                                    @endforeach
                                    {{ $kilo }}
                                </td>
                                <td>{{ $remito->nombretransporte ?? '' }}</td>
                                <td>
                                    {{ $remito['estado'] }}
                                    @if (empty($remito['venta_id']))
                                        <span class="badge badge-warning">Sin factura</span>
                                    @endif
                                </td>
                                <td>
                                    @if (can('editar-remitos', false))
                                        <a href="{{ route('editar_remito', ['id' => $remito['id']] + $retornoListadoQuery) }}"
                                           class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-remitos', false) && $remito['estado'] == 'Pendiente')
                                        <form action="{{ route('eliminar_remito', ['id' => $remito['id']]) }}"
                                              class="d-inline form-eliminar" method="POST">
                                            @csrf @method('delete')
                                            <button type="submit" onclick="eliminarRemito(event)"
                                                    class="btn-accion-tabla eliminar tooltipsC"
                                                    title="Eliminar este registro">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if (can('listar-remitos', false))
                                        <a href="{{ route('listar_remito_pdf', ['id' => $remito['id']]) }}"
                                           class="btn-accion-tabla tooltipsC" title="Listar el remito en PDF">
                                            <i class="fas fa-file-pdf text-danger"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center">Sin remitos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $remitos->appends($filtrosQuery ?? [])->links() }}

@if (\App\Services\Ventas\RemitoImportarDesdeAnitaService::esElBierzo() && can('ejecutar-importar-remito-anita', false))
    @include('ventas.remito.partials.modal_importar_anita')
@endif
@endsection
