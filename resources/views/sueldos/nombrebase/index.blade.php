@extends("theme.$theme.layout")
@section('titulo')
    Nombres de bases de sueldos
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
                <h3 class="card-title">Nombres de bases de sueldos</h3>
                <div class="card-tools">
                    @if (can('actualizar-nombrebase-sueldos', false))
                        <form action="{{route('sincronizar_nombrebase_sueldos')}}" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Sincronizar las bases desde Anita? Solo se agregarán las que falten.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                    @endif
                    @if (can('crear-nombrebase-sueldos', false))
                        <a href="{{route('crear_nombrebase_sueldos')}}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>
                                @if (can('editar-nombrebase-sueldos', false))
                                    <a href="{{route('editar_nombrebase_sueldos', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-nombrebase-sueldos', false))
                                    <form action="{{route('eliminar_nombrebase_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
