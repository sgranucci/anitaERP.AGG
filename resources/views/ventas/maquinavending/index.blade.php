@extends("theme.$theme.layout")
@section('titulo')
    M&aacute;quinas vending
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinMaquinasCargadas ?? false))
        <div class="alert alert-info">
            @if (config('app.anita_sync_maquinavending_gastronomia_index'))
            No hay m&aacute;quinas vending en el ERP. Para importar desde Anita (Biyemas, Kandiko y Rebisco) ejecute en el servidor:
            <code>php artisan maquinavending:sincronizar-anita</code>
            @else
            No hay m&aacute;quinas vending en el ERP. Cree registros con <strong>Nuevo registro</strong>.
            @endif
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">M&aacute;quinas vending</h3>
                <div class="card-tools">
                    @include('includes.ventas.boton-manual-vending')
                    @if (config('app.anita_sync_maquinavending_gastronomia_index') && can('sincronizar-maquinavending-gastronomia-anita', false))
                    <form action="{{ route('sincronizar_maquinavending_gastronomia_anita') }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Importar máquinas vending y rulos desde Anita (Biyemas, Kandiko y Rebisco)?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                        </button>
                    </form>
                    @endif
                    @if (can('crear-maquinavending-gastronomia', false))
                    <a href="{{ route('crear_maquinavending_gastronomia') }}" class="btn btn-outline-secondary btn-sm">
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
                            <th>Empresa</th>
                            <th>Nombre</th>
                            <th>C&oacute;d. Anita</th>
                            <th>Punto de venta</th>
                            <th>Ubicaci&oacute;n</th>
                            <th>Dep&oacute;sito</th>
                            <th>C&oacute;d. ARCA</th>
                            <th>N&deg; serie</th>
                            <th>Rulos</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->codigo_anita ?: '—' }}</td>
                            <td>
                                @if ($data->puntoventa)
                                    {{ $data->puntoventa->codigo }} — {{ $data->puntoventa->nombre }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ optional($data->ubicacion)->nombre }}</td>
                            <td>
                                @if ($data->deposito)
                                    {{ $data->deposito->codigo }} — {{ $data->deposito->nombre }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $data->codigo_arca ?: '—' }}</td>
                            <td>{{ $data->numero_serie ?: '—' }}</td>
                            <td>{{ $data->articulos_count ?? 0 }}</td>
                            <td>
                                @if (can('editar-maquinavending-gastronomia', false))
                                    <a href="{{ route('editar_maquinavending_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-maquinavending-gastronomia', false))
                                <form action="{{ route('eliminar_maquinavending_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
