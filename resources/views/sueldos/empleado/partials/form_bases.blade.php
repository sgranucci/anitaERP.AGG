@php
    $puedeEditarBases = can('actualizar-empleado-sueldos', false);
    $puedeBorrarVigencia = $puedeBorrarVigencia ?? can('borrar-vigencia-empleado-sueldos', false);
    // En empleado: editable cuando la categoría NO usa tabla (origen C)
    $basesEditables = ! ($usaTabla ?? true);
@endphp

<div class="row">
<div class="col-lg-5 mb-3 mb-lg-0">

<div id="host-set-conceptos" class="mb-3"
     data-url="{{ route('set_conceptos_empleado_sueldos', ['empleado' => $data->id]) }}">
    <div class="text-center text-muted py-3 small">
        <i class="fa fa-spinner fa-spin"></i> Cargando grupos / set efectivo…
    </div>
</div>

@if ($usaTabla)
    <div class="alert alert-info py-2">
        <i class="fa fa-table"></i>
        Bases desde la <strong>tabla de la categoría</strong> (solo lectura).
    </div>
@else
    <div class="alert alert-warning py-2">
        <i class="fa fa-user"></i>
        Bases cargadas <strong>en el empleado</strong>, con vigencia.
    </div>
@endif

<div id="bases-empleado-panel"
     data-empleado-id="{{ $data->id }}"
     data-usa-tabla="{{ $basesEditables ? 1 : 0 }}"
     data-puede-editar="{{ ($basesEditables && $puedeEditarBases) ? 1 : 0 }}"
     data-puede-borrar="{{ ($basesEditables && $puedeBorrarVigencia) ? 1 : 0 }}"
     data-csrf="{{ csrf_token() }}"
     data-hoy="{{ now()->format('Y-m-d') }}"
     data-url-guardar="{{ route('guardar_base_empleado_sueldos', ['id' => $data->id]) }}"
     data-url-lote="{{ route('guardar_vigencias_empleado_sueldos', ['id' => $data->id]) }}"
     data-url-bases="{{ route('bases_empleado_sueldos', ['id' => $data->id]) }}"
     data-url-historial="{{ route('historial_bases_empleado_sueldos', ['id' => $data->id]) }}"
     data-url-actualizar="{{ route('actualizar_vigencia_empleado_sueldos', ['id' => $data->id, 'baseId' => 'BASEID']) }}"
     data-url-eliminar="{{ route('eliminar_base_empleado_sueldos', ['id' => $data->id, 'baseId' => 'BASEID']) }}"
     data-url-eliminar-base="{{ route('eliminar_base_completa_empleado_sueldos', ['id' => $data->id, 'nombrebaseId' => 'NBID']) }}">

    <div class="d-flex justify-content-between align-items-center mb-2">
        <small class="text-muted">
            <i class="fa fa-info-circle"></i>
            Abrí una base ({{ $basesEditables && $puedeEditarBases ? 'gestionar' : 'ver' }} vigencias) con <i class="fa fa-list-ol"></i>.
        </small>
        @if ($basesEditables && $puedeEditarBases)
        <button type="button" id="btn-nueva-base" class="btn btn-primary btn-sm">
            <i class="fa fa-plus-circle"></i> Agregar base
        </button>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered" id="tabla-bases-vigentes">
            <thead class="thead-light">
                <tr>
                    <th class="width20">Cód. base</th>
                    <th>Base</th>
                    <th class="text-right">Valor vigente</th>
                    <th class="width20">Vigencia</th>
                    <th class="width80" data-orderable="false"></th>
                </tr>
            </thead>
            <tbody id="tbody-bases-vigentes">
                @forelse ($basesGrilla as $base)
                    <tr data-base-id="{{ $base['id'] }}"
                        data-nombrebase-id="{{ $base['nombrebase_id'] }}"
                        data-nombrebase-desc="{{ $base['nombrebase_descripcion'] }}"
                        data-valor="{{ $base['editar_valor'] }}">
                        <td>{{ $base['nombrebase_codigo'] }}</td>
                        <td>{{ $base['nombrebase_descripcion'] }}</td>
                        <td class="text-right">
                            @if ($base['tiene_vigente'])
                                {{ $base['valor_fmt'] }}
                            @else
                                <span class="text-muted font-italic">sin vigencia hoy</span>
                            @endif
                            @if (! empty($base['proxima']))
                                <div class="small text-primary">
                                    <i class="fa fa-clock"></i> Próxima: {{ $base['proxima']['valor_fmt'] }}
                                    <span class="text-muted">desde {{ $base['proxima']['fecha_vigencia_fmt'] }}</span>
                                </div>
                            @endif
                        </td>
                        <td>{{ $base['fecha_vigencia_fmt'] ?? '—' }}</td>
                        <td class="text-nowrap">
                            <button type="button" class="btn-accion-tabla tooltipsC btn-gestionar-vigencias"
                                    title="{{ $basesEditables && $puedeEditarBases ? 'Gestionar vigencias' : 'Ver vigencias' }}"
                                    data-nombrebase-id="{{ $base['nombrebase_id'] }}"
                                    data-nombrebase-desc="{{ $base['nombrebase_descripcion'] }}">
                                <i class="fa fa-list-ol"></i>
                            </button>
                            @if ($basesEditables && $puedeBorrarVigencia)
                                <button type="button" class="btn-accion-tabla tooltipsC text-danger btn-eliminar-base-completa"
                                        title="Eliminar base completa"
                                        data-nombrebase-id="{{ $base['nombrebase_id'] }}"
                                        data-nombrebase-desc="{{ $base['nombrebase_descripcion'] }}">
                                    <i class="fa fa-trash"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr id="fila-sin-bases"><td colspan="5" class="text-center text-muted">Sin bases cargadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- /col-lg-5 bases --}}
<div class="col-lg-7">
    @include('sueldos.empleado.partials.liquidacion_preview')
</div>
</div>{{-- /row split --}}
