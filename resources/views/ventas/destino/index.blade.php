@extends("theme.$theme.layout")
@section('titulo')
    Destinos SENASA
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/destino/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\DestinoListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Destinos SENASA</h3>
                <div class="card-tools d-flex flex-nowrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-destino',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => DestinoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('destino'),
                        'placeholder' => 'Localidad, zona o código SENASA',
                        'toggleTarget' => '#panel-filtros-destino',
                        'toggleId' => 'btn-toggle-filtros-destino',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_destino', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-destino',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('destino') }}" id="form-filtros-destino" class="mb-0">
                @include('ventas.destino.partials.filtros_listado', [
                    'limpiarUrl' => route('destino'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                <div class="px-2 pt-1 d-flex flex-nowrap align-items-center">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_destino',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Código zona</th>
                            <th>Zona de venta</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th>País</th>
                            <th>Patagónico</th>
                            <th>Código de localidad SENASA</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->zonavta->nombre ?? '' }}</td>
                            <td>{{ $data->localidad }}</td>
                            <td>{{ $data->provincia }}</td>
                            <td>{{ $data->pais_codigo }}</td>
                            <td>{{ $data->patagonico ? 'Sí' : 'No' }}</td>
                            <td>{{ $data->codigo_localidad_senasa }}</td>
                            <td class="text-nowrap">
                                @if (can('editar-destino', false))
                                    <a href="{{ route('editar_destino', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-destino', false))
                                <form action="{{ route('eliminar_destino', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
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
