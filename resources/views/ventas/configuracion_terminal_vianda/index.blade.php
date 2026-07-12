@extends("theme.$theme.layout")
@section('titulo')
    Configuración terminal de viandas
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
                <h3 class="card-title">Terminales de viandas (por PC)</h3>
                <div class="card-tools">
                    @if (can('crear-configuracion-terminal-vianda', false))
                    <a href="{{ route('crear_configuracion_terminal_vianda') }}" class="btn btn-outline-secondary btn-sm">
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
                            <th>Identificador PC</th>
                            <th>Nombre</th>
                            <th>Ubicación</th>
                            <th>Empresa</th>
                            <th>Dep. platos</th>
                            <th>Dep. insumos</th>
                            <th>Tipo transacción</th>
                            <th>Salida voucher</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->identificador_pc }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ optional($data->ubicacion)->nombre }}</td>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>{{ optional($data->depositoPlatos)->codigo }} — {{ optional($data->depositoPlatos)->nombre }}</td>
                            <td>{{ optional($data->depositoInsumos)->codigo }} — {{ optional($data->depositoInsumos)->nombre }}</td>
                            <td>{{ optional($data->tipotransaccion)->abreviatura }} — {{ optional($data->tipotransaccion)->nombre }}</td>
                            <td>{{ optional($data->salidaVoucher)->nombre }}</td>
                            <td>{{ $data->etiquetaEstado() }}</td>
                            <td>
                                @if (can('editar-configuracion-terminal-vianda', false))
                                    <a href="{{ route('editar_configuracion_terminal_vianda', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-configuracion-terminal-vianda', false))
                                <form action="{{ route('eliminar_configuracion_terminal_vianda', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
