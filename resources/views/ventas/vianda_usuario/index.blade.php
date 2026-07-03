@extends("theme.$theme.layout")
@section('titulo')
    Usuarios de vianda
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinRegistros ?? false))
        <div class="alert alert-info">
            @if (config('app.anita_sync_vianda_usuario_gastronomia_index'))
            No hay usuarios de vianda en el ERP. Use <strong>Sincronizar desde Anita</strong> o ejecute:
            <code>php artisan vianda:sincronizar-usuarios-anita</code>
            @else
            No hay usuarios de vianda. Cree registros con <strong>Nuevo registro</strong>.
            @endif
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Usuarios de vianda</h3>
                <div class="card-tools">
                    @if (config('app.anita_sync_vianda_usuario_gastronomia_index') && can('sincronizar-vianda-usuario-gastronomia-anita', false))
                    <form action="{{ route('sincronizar_vianda_usuario_gastronomia_anita') }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Importar usuarios de vianda desde Anita? Puede tardar unos minutos.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                        </button>
                    </form>
                    @endif
                    @if (can('crear-vianda-usuario-gastronomia', false))
                    <a href="{{ route('crear_vianda_usuario_gastronomia') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('consultar_vianda_usuario_gastronomia') }}" class="form-inline mb-3">
                    <div class="form-group mr-2 mb-2">
                        <input type="text" name="busqueda" class="form-control form-control-sm" placeholder="Nombre o c&oacute;digo&hellip;"
                               value="{{ $filtros['busqueda'] ?? '' }}">
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <select name="estado" class="form-control form-control-sm">
                            <option value="">Estado (todos)</option>
                            <option value="A" {{ ($filtros['estado'] ?? '') === 'A' ? 'selected' : '' }}>Activo</option>
                            <option value="I" {{ ($filtros['estado'] ?? '') === 'I' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <select name="tipo_usuario" class="form-control form-control-sm">
                            <option value="">Tipo (todos)</option>
                            @foreach ($tiposUsuario as $cod => $etiq)
                                <option value="{{ $cod }}" {{ ($filtros['tipo_usuario'] ?? '') === $cod ? 'selected' : '' }}>{{ $cod }} — {{ $etiq }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mb-2">Buscar</button>
                    @if (\App\Support\Ventas\ViandaUsuarioListadoFiltros::tieneCriteriosAplicados($filtros))
                    <a href="{{ route('consultar_vianda_usuario_gastronomia') }}" class="btn btn-link btn-sm mb-2">Limpiar</a>
                    @endif
                </form>
                <div class="table-responsive p-0">
                    <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                        <thead>
                            <tr>
                                <th class="width20">ID</th>
                                <th>C&oacute;digo</th>
                                <th>Nombre</th>
                                <th>Centro costo</th>
                                <th>Tipo</th>
                                <th>Tipo men&uacute;</th>
                                <th class="width80">Estado</th>
                                <th class="width80" data-orderable="false"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td>{{ $data->codigo_usuario }}</td>
                                <td>{{ $data->nombre }}</td>
                                <td>
                                    @if ($data->centrocosto)
                                        {{ $data->centrocosto->codigo }} — {{ $data->centrocosto->nombre }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ \App\Support\Ventas\ViandaUsuarioTipoSupport::etiqueta($data->tipo_usuario) }}</td>
                                <td>{{ optional($data->tipoMenu)->nombre ?: '—' }}</td>
                                <td>{{ $data->etiquetaEstado() }}</td>
                                <td>
                                    @if (can('editar-vianda-usuario-gastronomia', false))
                                        <a href="{{ route('editar_vianda_usuario_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-vianda-usuario-gastronomia', false))
                                    <form action="{{ route('eliminar_vianda_usuario_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
                <div class="mt-2">
                    @if ($datas->total() > 0)
                        <small class="text-muted">
                            {{ $datas->firstItem() }}–{{ $datas->lastItem() }} de {{ $datas->total() }} registros
                        </small>
                    @endif
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
