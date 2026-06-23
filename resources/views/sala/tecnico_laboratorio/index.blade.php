@extends("theme.$theme.layout")
@section('titulo')
    T&eacute;cnicos de laboratorio
@endsection

@section("scripts")
@include('includes.datatable-export-titulo', ['titulo' => 'Técnicos de laboratorio', 'nombreArchivo' => 'tecnicos_laboratorio'])
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">T&eacute;cnicos de laboratorio</h3>
                <div class="card-tools">
                    <a href="{{ route('crear_tecnico_laboratorio') }}" class="btn btn-outline-secondary btn-sm">
                        @if (can('crear-tecnico-laboratorio', false))
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
                            <th>Legajo</th>
                            <th>Activo</th>
                            <th>Empresa</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->legajo ?? '' }}</td>
                            <td>{{ $data->activo === 'S' ? 'Sí' : 'No' }}</td>
                            <td>{{ $data->empresas->nombre ?? '' }}</td>
                            <td>
                                @if (can('editar-tecnico-laboratorio', false))
                                    <a href="{{ route('editar_tecnico_laboratorio', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-tecnico-laboratorio', false))
                                <form action="{{ route('eliminar_tecnico_laboratorio', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
