@extends("theme.$theme.layout")
@section('titulo')
Listas de precio — proveedores
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Listas de precio de proveedores</h3>
                <div class="card-tools">
                    @if (can('crear-listaprecio-proveedor', false))
                    <a href="{{ route('crear_listaprecio_proveedor') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                    </a>
                    @endif
                </div>
                <div class="d-md-flex justify-content-md-end">
                    <form action="{{ route('consultar_listaprecio_proveedor') }}" method="GET">
                        <div class="btn-group">
                            <input type="text" name="busqueda" class="form-control" placeholder="Búsqueda ..." value="{{ $busqueda ?? '' }}">
                            <button type="submit" class="btn btn-default">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla', ['ruta' => 'listar_listaprecio_proveedor', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>Fecha</th>
                            <th>Nombre</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>Usuario</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
                            <td><small>{{ $data->nombre }}</small></td>
                            <td><small>{{ $data->nombreproveedor }}</small></td>
                            <td><small>{{ $data->estado }}</small></td>
                            <td><small>{{ $data->nombreusuario }}</small></td>
                            <td>
                                @if (can('editar-listaprecio-proveedor', false))
                                <a href="{{ route('editar_listaprecio_proveedor', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (can('editar-listaprecio-proveedor', false) && can('actualizar-listaprecio-proveedor', false))
                                <a href="{{ route('editar_listaprecio_proveedor', ['id' => $data->id]) }}#importar-excel" class="btn-accion-tabla tooltipsC text-success" title="Importar precios desde Excel">
                                    <i class="fa fa-file-excel-o"></i>
                                </a>
                                @endif
                                @if (can('actualizar-listaprecio-proveedor', false))
                                <form action="{{ route('cambiar_estado_listaprecio_proveedor', ['id' => $data->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Cambiar estado entre ACTIVA e INACTIVA?');">
                                    @csrf
                                    <button type="submit" class="btn-accion-tabla tooltipsC text-warning" title="Cambiar estado ACTIVA / INACTIVA">
                                        <i class="fa fa-toggle-on"></i>
                                    </button>
                                </form>
                                @endif
                                @if (can('borrar-listaprecio-proveedor', false))
                                <form action="{{ route('eliminar_listaprecio_proveedor', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
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
            @if(method_exists($listas, 'links'))
            <div class="card-footer">
                {{ $listas->appends(request()->query())->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
