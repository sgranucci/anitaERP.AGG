@extends("theme.$theme.layout")
@section('titulo')
    Configuración Ingresos Brutos
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Configuración Ingresos Brutos (ARBA)</h3>
                <div class="card-tools">
                    @if (can('crear-ingresos-brutos-config', false))
                        <a href="{{ route('crear_ingresos_brutos_config') }}" class="btn btn-outline-secondary btn-sm">
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
                            <th>Provincia</th>
                            <th>Tipo</th>
                            <th>Nombre</th>
                            <th>Actividad ARBA</th>
                            <th>Cuentas</th>
                            <th>Activo</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td>{{ $data->provincia->nombre ?? '—' }}</td>
                                <td>{{ \App\Models\Contable\Iibb_Presentacion_Config::$enumTipo[$data->tipo] ?? $data->tipo }}</td>
                                <td>{{ $data->nombre }}</td>
                                <td>{{ $data->codigo_actividad_arba }}</td>
                                <td class="small">
                                    @include('contable.partials.config_cuentas_index', ['cuentas' => $data->cuentas])
                                </td>
                                <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                                <td>
                                    @if (can('editar-ingresos-brutos-config', false))
                                        <a href="{{ route('editar_ingresos_brutos_config', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-ingresos-brutos-config', false))
                                        <form action="{{ route('eliminar_ingresos_brutos_config', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method('delete')
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
