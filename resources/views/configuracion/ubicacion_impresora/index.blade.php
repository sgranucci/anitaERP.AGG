@extends("theme.$theme.layout")
@section('titulo')
    Ubicaciones de impresora
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/ubicacion_impresora/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Configuracion\UbicacionImpresoraListadoFiltros;
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ubicaciones de impresora</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-ubicacion-impresora',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => UbicacionImpresoraListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('ubicacion_impresora'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-ubicacion-impresora',
                        'toggleId' => 'btn-toggle-filtros-ubicacion-impresora',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_ubicacion_impresora'),
                        'nuevoRegistroCan' => 'crear-ubicacion-impresora',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('ubicacion_impresora') }}" id="form-filtros-ubicacion-impresora" class="mb-0">
                @include('configuracion.ubicacion_impresora.partials.filtros_listado', [
                    'limpiarUrl' => route('ubicacion_impresora'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_ubicacion_impresora',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Descripci&oacute;n</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>
                                @if (can('editar-ubicacion-impresora', false))
                                    <a href="{{ route('editar_ubicacion_impresora', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-ubicacion-impresora', false))
                                <form action="{{ route('eliminar_ubicacion_impresora', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
