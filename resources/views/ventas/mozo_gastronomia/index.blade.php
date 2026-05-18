@extends("theme.$theme.layout")
@section('titulo')
    Mozos gastronomía
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinMozosCargados ?? false))
        <div class="alert alert-info">
            @if (config('app.anita_sync_mozo_gastronomia_index'))
            No hay mozos en el ERP. Para importar desde Anita ejecute en el servidor:
            <code>php artisan mozo-gastronomia:sincronizar-anita</code>
            @else
            No hay mozos en el ERP. Cree registros con <strong>Nuevo registro</strong>.
            @endif
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Mozos gastronomía</h3>
                <div class="card-tools">
                    @if (can('crear-mozo-gastronomia', false))
                    <a href="{{ route('crear_mozo_gastronomia') }}" class="btn btn-outline-secondary btn-sm">
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
                            <th>Código Anita</th>
                            <th>Empresa</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>
                                @if (can('editar-mozo-gastronomia', false))
                                    <a href="{{ route('editar_mozo_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-mozo-gastronomia', false))
                                <form action="{{ route('eliminar_mozo_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
