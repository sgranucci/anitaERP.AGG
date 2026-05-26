@extends("theme.$theme.layout")
@section('titulo')
    Categorías de fidelidad gastronomía
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinCategoriasCargadas ?? false))
        <div class="alert alert-info">
            @if (config('app.anita_sync_categoria_fidelidad_gastronomia_index'))
            No hay categorías de fidelidad en el ERP. Para importar desde Anita use el botón <strong>Sincronizar desde Anita</strong> o ejecute en el servidor:
            <code>php artisan categoria-fidelidad-gastronomia:sincronizar-anita</code>
            @else
            No hay categorías de fidelidad en el ERP. Cree registros con <strong>Nuevo registro</strong>.
            @endif
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Categorías de fidelidad gastronomía</h3>
                <div class="card-tools">
                    @if (can('crear-categoria-fidelidad-gastronomia', false))
                    <a href="{{ route('crear_categoria_fidelidad_gastronomia') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                    </a>
                    @endif
                    @if (config('app.anita_sync_categoria_fidelidad_gastronomia_index') && can('actualizar-categoria-fidelidad-gastronomia', false))
                    <form action="{{ route('sincronizar_categoria_fidelidad_gastronomia_anita') }}" method="POST" class="d-inline" onsubmit="return confirm('La sincronizaci\u00f3n importa clicat, clicatart y entregas clicatent desde Anita (entregas desde {{ config('categoriafidelidad_gastronomia_anita.fecha_desde') }}).\n\n\u00bfContinuar?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm" title="Importar clicat, clicatart y clicatent desde Anita (ApiAnita)">
                            <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Código Anita</th>
                            <th>Artículos canjeables</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>
                                @if ($data->articulos->isEmpty())
                                    <span class="text-muted">—</span>
                                @else
                                    @foreach ($data->articulos as $linea)
                                        @if ($linea->articulo)
                                            <small class="d-block">{{ $linea->articulo->sku }} — {{ $linea->articulo->descripcion }}</small>
                                        @endif
                                    @endforeach
                                @endif
                            </td>
                            <td>
                                @if (can('editar-categoria-fidelidad-gastronomia', false))
                                    <a href="{{ route('editar_categoria_fidelidad_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-categoria-fidelidad-gastronomia', false))
                                <form action="{{ route('eliminar_categoria_fidelidad_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
