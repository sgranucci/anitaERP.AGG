@extends("theme.$theme.layout")
@section('titulo')
    Tótems Waitry gastronomía
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
                <h3 class="card-title">Tótems Waitry</h3>
                <div class="card-tools">
                    @if (can('crear-totem-waitry-gastronomia', false))
                    <a href="{{ route('crear_totem_waitry_gastronomia') }}" class="btn btn-outline-secondary btn-sm">
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
                            <th>Ubicación</th>
                            <th>Table ID Waitry</th>
                            <th>Detalle</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>{{ optional($data->ubicacion)->nombre }}</td>
                            <td>{{ $data->waitry_table_id ?: '—' }}</td>
                            <td>{{ $data->detalle }}</td>
                            <td>
                                @if (can('editar-totem-waitry-gastronomia', false))
                                    <a href="{{ route('editar_totem_waitry_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-totem-waitry-gastronomia', false))
                                <form action="{{ route('eliminar_totem_waitry_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
