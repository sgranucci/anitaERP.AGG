@php
    use App\Support\Sueldos\CategoriaOrigenBases;
    $puedeEditarBases = can('actualizar-categoria-sueldos', false);
    $puedeBorrarVigencia = $puedeBorrarVigencia ?? can('borrar-vigencia-categoria-sueldos', false);
@endphp

@if (! $usaTabla)
    <div class="alert alert-warning">
        <i class="fa fa-user"></i>
        Esta categoría toma las bases <strong>desde cada empleado</strong>. No se cargan bases a nivel de la categoría.
        Cambie el «Origen de las bases» a «Desde la categoría» si desea cargarlas aquí.
    </div>
@endif

<div id="bases-categoria-panel"
     data-categoria-id="{{ $data->id }}"
     data-usa-tabla="{{ $usaTabla ? 1 : 0 }}"
     data-puede-editar="{{ $puedeEditarBases ? 1 : 0 }}"
     data-puede-borrar="{{ $puedeBorrarVigencia ? 1 : 0 }}"
     data-csrf="{{ csrf_token() }}"
     data-hoy="{{ now()->format('Y-m-d') }}"
     data-url-guardar="{{ route('guardar_base_categoria_sueldos', ['id' => $data->id]) }}"
     data-url-lote="{{ route('guardar_vigencias_categoria_sueldos', ['id' => $data->id]) }}"
     data-url-bases="{{ route('bases_categoria_sueldos', ['id' => $data->id]) }}"
     data-url-historial="{{ route('historial_bases_categoria_sueldos', ['id' => $data->id]) }}"
     data-url-actualizar="{{ route('actualizar_vigencia_categoria_sueldos', ['id' => $data->id, 'baseId' => 'BASEID']) }}"
     data-url-eliminar="{{ route('eliminar_base_categoria_sueldos', ['id' => $data->id, 'baseId' => 'BASEID']) }}"
     data-url-eliminar-base="{{ route('eliminar_base_completa_categoria_sueldos', ['id' => $data->id, 'nombrebaseId' => 'NBID']) }}">

    <div class="d-flex justify-content-between align-items-center mb-2">
        <small class="text-muted">
            <i class="fa fa-info-circle"></i>
            Abrí una base ({{ $usaTabla && $puedeEditarBases ? 'gestionar' : 'ver' }} vigencias) con <i class="fa fa-list-ol"></i>. Ahí podés editar, agregar o eliminar vigencias con su fecha.
        </small>
        @if ($usaTabla && $puedeEditarBases)
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
                                <div class="small text-primary" title="Versión programada a futuro">
                                    <i class="fa fa-clock"></i> Próxima: {{ $base['proxima']['valor_fmt'] }}
                                    <span class="text-muted">desde {{ $base['proxima']['fecha_vigencia_fmt'] }}</span>
                                    @if ($base['futuras_count'] > 1)
                                        <span class="badge badge-light">+{{ $base['futuras_count'] - 1 }}</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>{{ $base['fecha_vigencia_fmt'] ?? '—' }}</td>
                        <td class="text-nowrap">
                            <button type="button" class="btn-accion-tabla tooltipsC btn-gestionar-vigencias"
                                    title="{{ $usaTabla && $puedeEditarBases ? 'Gestionar vigencias' : 'Ver vigencias' }}"
                                    data-nombrebase-id="{{ $base['nombrebase_id'] }}"
                                    data-nombrebase-desc="{{ $base['nombrebase_descripcion'] }}">
                                <i class="fa fa-list-ol"></i>
                            </button>
                            @if ($usaTabla && $puedeBorrarVigencia)
                                <button type="button" class="btn-accion-tabla tooltipsC text-danger btn-eliminar-base-completa"
                                        title="Eliminar base completa (todas las vigencias)"
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

    @if ($puedeEditarBases)
        <div class="row mt-3">
            <div class="col-lg-3"></div>
            <div class="col-lg-6">
                <button type="submit" form="form-general" class="btn botonsubmit btn-success">Actualizar</button>
            </div>
        </div>
    @endif
</div>
