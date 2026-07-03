@extends("theme.$theme.layout")
@section('titulo')
    Tipos de men&uacute; de vianda
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
            @if (config('app.anita_sync_vianda_tipo_menu_gastronomia_index'))
            No hay tipos de men&uacute; de vianda en el ERP. Para importar desde Anita (tipomvianda / artmvianda) use
            <strong>Sincronizar desde Anita</strong> o ejecute:
            <code>php artisan vianda:sincronizar-tipos-menu-anita</code>
            @else
            No hay tipos de men&uacute; de vianda. Cree registros con <strong>Nuevo registro</strong>.
            @endif
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tipos de men&uacute; de vianda</h3>
                <div class="card-tools">
                    @if (config('app.anita_sync_vianda_tipo_menu_gastronomia_index') && can('sincronizar-vianda-tipo-menu-gastronomia-anita', false))
                    <form action="{{ route('sincronizar_vianda_tipo_menu_gastronomia_anita') }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Importar tipos de menú y artículos por día desde Anita?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                        </button>
                    </form>
                    @endif
                    @if (can('crear-vianda-tipo-menu-gastronomia', false))
                    <a href="{{ route('crear_vianda_tipo_menu_gastronomia') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th class="width80">Estado</th>
                            <th>C&oacute;d. Anita</th>
                            @foreach ($diasSemana as $dia => $etiqueta)
                            <th>{{ $etiqueta }}</th>
                            @endforeach
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->etiquetaEstado() }}</td>
                            <td>{{ $data->codigo_anita ?: '—' }}</td>
                            @foreach ($diasSemana as $dia => $etiqueta)
                            <td class="small">
                                @php
                                    $itemsDia = $data->articulos
                                        ->where('dia_semana', $dia)
                                        ->sortBy('orden')
                                        ->map(function ($linea) {
                                            $art = $linea->articulo;
                                            if ($art === null) {
                                                return null;
                                            }
                                            return trim($art->sku.' — '.$art->descripcion);
                                        })
                                        ->filter()
                                        ->values();
                                @endphp
                                @if ($itemsDia->isEmpty())
                                    <span class="text-muted">—</span>
                                @else
                                    @foreach ($itemsDia as $item)
                                        <div>{{ $item }}</div>
                                    @endforeach
                                @endif
                            </td>
                            @endforeach
                            <td>
                                @if (can('editar-vianda-tipo-menu-gastronomia', false))
                                    <a href="{{ route('editar_vianda_tipo_menu_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-vianda-tipo-menu-gastronomia', false))
                                <form action="{{ route('eliminar_vianda_tipo_menu_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
@endsection
