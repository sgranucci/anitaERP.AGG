@extends("theme.$theme.layout")
@section('titulo')
    {{ $def['titulo'] }}
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/seguridad/ingreso_proveedor_catalogo/filtro.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Seguridad\IngresoProveedorCatalogoListadoFiltros;
    $limpiarUrl = route($def['ruta']);
    $filtrosQuery = $filtrosQuery ?? [];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $def['titulo'] }}</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-ingreso-catalogo',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => IngresoProveedorCatalogoListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda…',
                        'toggleTarget' => '#panel-filtros-ingreso-catalogo',
                        'toggleId' => 'btn-toggle-filtros-ingreso-catalogo',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_'.$def['ruta']),
                        'nuevoRegistroCan' => 'crear-ingreso-proveedor-catalogo',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route($def['ruta']) }}" id="form-filtros-ingreso-catalogo" class="mb-0">
                @include('seguridad.ingreso_proveedor_catalogo.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>C&oacute;digo</th>
                            <th>Nombre</th>
                            <th>Activo</th>
                            <th class="text-nowrap" style="width:80px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td>{{ $data->codigo }}</td>
                                <td>{{ $data->nombre }}</td>
                                <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                                <td class="text-nowrap align-middle">
                                    @if (can('editar-ingreso-proveedor-catalogo', false))
                                        <a href="{{ route('editar_'.$def['ruta'], ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-ingreso-proveedor-catalogo', false))
                                        <form action="{{ route('eliminar_'.$def['ruta'], ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery)->links() }}
@endsection
