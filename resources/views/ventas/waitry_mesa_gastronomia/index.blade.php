@extends("theme.$theme.layout")
@section('titulo')
    Mesas Waitry
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
                <h3 class="card-title">Mesas Waitry por servicio</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_configuracion_puntoventa_gastronomia') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> PCs de gastronomía
                    </a>
                    @if (can('crear-configuracion-puntoventa-gastronomia', false))
                    <a href="{{ route('crear_waitry_mesa_gastronomia') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nueva mesa
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Cada fila es una mesa de Waitry (Mostrador, VIP, Mood). Después se asigna a la PC de gastronomía.
                    Mood se carga acá cuando Waitry informe el tableId. La comanda de esa PC sale con ese tableId.
                </p>
                <div class="table-responsive p-0">
                    <table class="table table-striped table-bordered table-hover" id="tabla-data">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th class="width20">ID</th>
                                <th>Empresa</th>
                                <th>Servicio</th>
                                <th>Mesa</th>
                                <th>tableId</th>
                                <th>Activa</th>
                                <th class="width80" data-orderable="false"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td>{{ optional($data->empresa)->nombre }}</td>
                                <td>{{ $data->servicio }}</td>
                                <td>{{ $data->nombre }}</td>
                                <td>{{ $data->table_id }}</td>
                                <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                                <td>
                                    @if (can('editar-configuracion-puntoventa-gastronomia', false))
                                        <a href="{{ route('editar_waitry_mesa_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-configuracion-puntoventa-gastronomia', false))
                                    <form action="{{ route('eliminar_waitry_mesa_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
</div>
@endsection
