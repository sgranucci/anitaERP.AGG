@extends("theme.$theme.layout")
@section('titulo')
    Índices de actualización
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
                <h3 class="card-title">&Iacute;ndices de actualizaci&oacute;n</h3>
                <div class="card-tools">
                    @if (can('crear-bien-uso-indice', false))
                        <a href="{{ route('crear_bien_uso_indice') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nuevo &iacute;ndice
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>C&oacute;digo</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th class="width80"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td>{{ $data->codigo }}</td>
                                <td>{{ $data->nombre }}</td>
                                <td>{{ $data->tipo_valor }}</td>
                                <td>
                                    @if (can('editar-bien-uso-indice', false))
                                        <a href="{{ route('editar_bien_uso_indice', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-bien-uso-indice', false))
                                        <form action="{{ route('eliminar_bien_uso_indice', $data->id) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method('delete')
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Sin &iacute;ndices</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($datas, 'links'))
                <div class="card-footer">{{ $datas->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
