@extends("theme.$theme.layout")
@section('titulo')
    Configuración SICORE
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
                <h3 class="card-title">Configuración SICORE</h3>
                <div class="card-tools">
                    <a href="{{ route('crear_sicore_config') }}" class="btn btn-outline-secondary btn-sm">
                        @if (can('crear-sicore-config', false))
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
                            <th>Cód. imp.</th>
                            <th>Régimen</th>
                            <th>Nombre</th>
                            <th>Criterio</th>
                            <th>Concilia con</th>
                            <th>Frecuencia</th>
                            <th>Haberes</th>
                            <th>Cuentas</th>
                            <th>Activo</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                            <tr>
                                <td>{{ $data->id }}</td>
                                <td>{{ $data->codigo_impuesto }}</td>
                                <td>{{ $data->codigo_regimen ?? '—' }}</td>
                                <td>{{ $data->nombre }}</td>
                                <td>{{ \App\Models\Contable\Sicore_Config::$enumCriterio[$data->criterio] ?? $data->criterio }}</td>
                                <td>{{ \App\Models\Contable\Sicore_Config::$enumConciliaCon[$data->concilia_con] ?? $data->concilia_con }}</td>
                                <td>{{ \App\Models\Contable\Sicore_Config::$enumFrecuencia[$data->frecuencia] ?? $data->frecuencia }}</td>
                                <td class="small">
                                    @if ($data->criterio === 'sueldos')
                                        Ret: {{ $data->concepto_retencion_sueldos ?? '—' }}
                                        @if ($data->concepto_devolucion_sueldos)
                                            <br>Dev: {{ $data->concepto_devolucion_sueldos }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small">
                                    @include('contable.partials.config_cuentas_index', ['cuentas' => $data->cuentas])
                                </td>
                                <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                                <td>
                                    @if (can('editar-sicore-config', false))
                                        <a href="{{ route('editar_sicore_config', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-sicore-config', false))
                                        <form action="{{ route('eliminar_sicore_config', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
