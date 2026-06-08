@extends("theme.$theme.layout")
@section('titulo')
    Configuración punto de venta gastronomía
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
                <h3 class="card-title">Configuración por PC / terminal</h3>
                <div class="card-tools">
                    @if (can('crear-configuracion-puntoventa-gastronomia', false))
                    <a href="{{ route('crear_configuracion_puntoventa_gastronomia') }}" class="btn btn-outline-secondary btn-sm">
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
                            <th>Descripción</th>
                            <th>Empresa</th>
                            <th>PV CAE</th>
                            <th>PV CAEA</th>
                            <th>Ubicación</th>
                            <th>Lista precios</th>
                            <th>Dep. venta</th>
                            <th>Dep. insumos</th>
                            <th>Tipo factura</th>
                            <th>Salida comandas</th>
                            <th>Salida facturas</th>
                            <th>Waitry</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->identificador_pc }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>{{ optional($data->puntoventaCae)->codigo }} — {{ optional($data->puntoventaCae)->nombre }}</td>
                            <td>{{ optional($data->puntoventaCaea)->codigo }} — {{ optional($data->puntoventaCaea)->nombre }}</td>
                            <td>{{ optional($data->ubicacion)->nombre ?? 'Todas' }}</td>
                            <td>{{ optional($data->listaprecio)->nombre ?? '—' }}</td>
                            <td>{{ optional($data->depositoVenta)->codigo }} — {{ optional($data->depositoVenta)->nombre ?? '—' }}</td>
                            <td>{{ optional($data->depositoInsumos)->codigo }} — {{ optional($data->depositoInsumos)->nombre ?? '—' }}</td>
                            <td>{{ optional($data->tipotransaccion)->abreviatura }} — {{ optional($data->tipotransaccion)->nombre }}</td>
                            <td>{{ optional($data->salidaComanda)->nombre }}</td>
                            <td>{{ optional($data->salidaFactura)->nombre }}</td>
                            <td>{{ ($data->waitry_habilitado ?? true) ? 'Sí' : 'No' }}</td>
                            <td>
                                @if (can('editar-configuracion-puntoventa-gastronomia', false))
                                    <a href="{{ route('editar_configuracion_puntoventa_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-configuracion-puntoventa-gastronomia', false))
                                <form action="{{ route('eliminar_configuracion_puntoventa_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
