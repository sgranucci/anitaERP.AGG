@extends("theme.$theme.layout")
@section('titulo')
    Prioridades de sala
@endsection

@section("scripts")
@include('includes.datatable-export-titulo', ['titulo' => 'Prioridades de sala', 'nombreArchivo' => 'prioridades_sala'])
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Prioridades de sala</h3>
                <div class="card-tools">
                    <a href="{{route('crear_prioridad_sala')}}" class="btn btn-outline-secondary btn-sm">
                        @if (can('crear-prioridad-sala', false))
                            <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                        @endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->empresas->nombre ?? ''}}</td>
                            <td>
                                @if (can('editar-prioridad-sala', false))
                                    <a href="{{route('editar_prioridad_sala', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-prioridad-sala', false))
                                <form action="{{route('eliminar_prioridad_sala', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
