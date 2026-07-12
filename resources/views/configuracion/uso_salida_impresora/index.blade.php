@extends("theme.$theme.layout")
@section('titulo')
    Usos de salida de impresión
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/uso_salida_impresora/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Configuracion\UsoSalidaImpresoraListadoFiltros;
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
                <h3 class="card-title">Usos de salida de impresión</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-uso-salida-impresora',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => UsoSalidaImpresoraListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('uso_salida_impresora'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-uso-salida-impresora',
                        'toggleId' => 'btn-toggle-filtros-uso-salida-impresora',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_uso_salida_impresora', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-uso-salida-impresora',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('uso_salida_impresora') }}" id="form-filtros-uso-salida-impresora" class="mb-0">
                @include('configuracion.uso_salida_impresora.partials.filtros_listado', [
                    'limpiarUrl' => route('uso_salida_impresora'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_uso_salida_impresora',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Programas destino</th>
                            <th>Descripci&oacute;n</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->programas_destino_etiqueta }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>
                                @if (can('editar-uso-salida-impresora', false))
                                    <a href="{{ route('editar_uso_salida_impresora', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-uso-salida-impresora', false))
                                <form action="{{ route('eliminar_uso_salida_impresora', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
