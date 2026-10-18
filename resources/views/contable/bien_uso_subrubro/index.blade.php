@extends("theme.$theme.layout")
@section('titulo')
    Subrubros de bienes de uso
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
                <h3 class="card-title">Subrubros de bienes de uso</h3>
                <div class="card-tools">
                    @if (can('crear-bien-uso-subrubro', false))
                        <a href="{{ route('crear_bien_uso_subrubro') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nuevo subrubro
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
                            <th>Rubro</th>
                            <th class="width80"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td>{{ $data->codigo }}</td>
                                <td>{{ $data->nombre }}</td>
                                <td>{{ optional($data->rubro)->codigo }} — {{ optional($data->rubro)->nombre }}</td>
                                <td>
                                    @if (can('editar-bien-uso-subrubro', false))
                                        <a href="{{ route('editar_bien_uso_subrubro', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-bien-uso-subrubro', false))
                                        <form action="{{ route('eliminar_bien_uso_subrubro', $data->id) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method('delete')
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Sin subrubros</td></tr>
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
