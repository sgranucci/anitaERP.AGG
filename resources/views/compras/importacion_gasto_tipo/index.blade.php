@extends("theme.$theme.layout")
@section('titulo')
    Tipos de gasto importación
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tipos de gasto de importación</h3>
                <div class="card-tools">
                    @if (can('crear-importacion-gasto-tipo', false))
                        <a href="{{ route('crear_importacion_gasto_tipo') }}" class="btn btn-primary btn-sm">Nuevo tipo</a>
                    @endif
                    <a href="{{ route('importacion') }}" class="btn btn-outline-info btn-sm">Importaciones</a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tratamiento</th>
                            <th>Prorrateo</th>
                            <th>Activo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td>{{ $data->codigo }}</td>
                                <td>{{ $data->nombre }}</td>
                                <td>{{ $data->tratamiento }}</td>
                                <td>{{ $data->base_prorrateo }}</td>
                                <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                                <td class="text-nowrap">
                                    @if (can('editar-importacion-gasto-tipo', false))
                                        <a href="{{ route('editar_importacion_gasto_tipo', $data->id) }}" class="btn-accion-tabla" title="Editar"><i class="fa fa-edit"></i></a>
                                    @endif
                                    @if (can('eliminar-importacion-gasto-tipo', false))
                                        <form action="{{ route('eliminar_importacion_gasto_tipo', $data->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla"><i class="fa fa-times-circle text-danger"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $datas->links() }}</div>
        </div>
    </div>
</div>
@endsection
