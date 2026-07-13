@extends("theme.$theme.layout")
@section('titulo')
Ubicaciones de stock
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
                <h3 class="card-title">Ubicaciones de stock (Anita)</h3>
                <div class="card-tools">
                    @if (can('listar-ubicaciones', false))
                        <form action="{{ route('sincronizar_ubicacion') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Traer / actualizar desde Anita">
                                <i class="fa fa-fw fa-sync"></i> Sync Anita
                            </button>
                        </form>
                    @endif
                    @if (can('crear-ubicaciones', false))
                        <a href="{{route('crear_ubicacion')}}" class="btn btn-outline-secondary btn-sm">
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
                            <th>C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Zona</th>
                            <th>&Aacute;rea</th>
                            <th>Nivel</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->codigo}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->zona}}</td>
                            <td>{{$data->area}}</td>
                            <td>{{$data->nivel}}</td>
                            <td>{{$data->etiquetaEstado()}}</td>
                            <td>
                                @if (can('editar-ubicaciones', false))
                                    <a href="{{route('editar_ubicacion', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-ubicaciones', false))
                                    <form action="{{route('eliminar_ubicacion', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
