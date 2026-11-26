@extends("theme.$theme.layout")
@section('titulo')
    Tipos de ahorro
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
                <h3 class="card-title">Tipos de ahorro</h3>
                <div class="card-tools">
                    @if (can('crear-tipo-ahorro', false))
                        <a href="{{ route('crear_tipoahorro') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nuevo tipo de ahorro
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th class="text-right">Orden</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td class="text-right">{{ $data->orden }}</td>
                            <td>
                                @if ($data->activo)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td>
                                @if (can('editar-tipo-ahorro', false))
                                    <a href="{{ route('editar_tipoahorro', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-tipo-ahorro', false))
                                    <form action="{{ route('eliminar_tipoahorro', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                        @csrf @method("delete")
                                        <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">No hay tipos de ahorro cargados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    @if ($datas->total() > 0)
                        {{ $datas->firstItem() }}&ndash;{{ $datas->lastItem() }} de {{ $datas->total() }}
                    @endif
                </small>
                {{ $datas->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
