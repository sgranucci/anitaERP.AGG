@extends("theme.$theme.layout")
@section('titulo')
    CAI
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script type="text/javascript">
$(function () {
    if ($.fn.DataTable.isDataTable('#tabla-data')) {
        $('#tabla-data').DataTable().order([[0, 'desc']]).draw();
    }
});
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">CAI remitos (ARCA)</h3>
                <div class="card-tools">
                    @if (can('crear-cai', false))
                        <a href="{{route('crear_cai')}}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">Orden</th>
                            <th>Tipo</th>
                            <th>Letra</th>
                            <th>Sucursal</th>
                            <th>Nro. CAI</th>
                            <th>Vencimiento</th>
                            <th>Descripción</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->orden }}</td>
                            <td>{{ $data->tipo }}</td>
                            <td>{{ $data->letra }}</td>
                            <td>{{ $data->sucursal }}</td>
                            <td>{{ $data->numero_cai }}</td>
                            <td data-order="{{ optional($data->fecha_vencimiento)->format('Ymd') }}">
                                {{ optional($data->fecha_vencimiento)->format('d/m/Y') }}
                            </td>
                            <td>{{ $data->descripcion }}</td>
                            <td>
                                @if (can('editar-cai', false))
                                    <a href="{{route('editar_cai', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-cai', false))
                                    <form action="{{route('eliminar_cai', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
