@php
    use App\Support\Sueldos\NovedadSueldosCatalogo;
    $hermanosMultiempresa = $hermanosMultiempresa ?? collect();
    $puedeReplicarMultiempresa = (bool) ($puedeReplicarMultiempresa ?? false);
@endphp
<div id="novedades-empleado-panel" data-empleado="{{ $empleado->id }}"
     data-url="{{ route('novedades_empleado_sueldos', ['empleado' => $empleado->id]) }}"
     data-url-replicar="{{ route('replicar_novedades_empleado_sueldos', ['empleado' => $empleado->id]) }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <div>
            <h5 class="mb-0"><i class="fa fa-bolt"></i> Novedades del per&iacute;odo</h5>
            <span class="text-muted small">Datos de entrada que consume el motor (<code>novedad()</code> / <code>novedad2()</code>).</span>
        </div>
        @if ($puedeReplicarMultiempresa)
            @php
                $empresasHermano = $hermanosMultiempresa->map(function ($h) {
                    $emp = $h->empresa;
                    if (! $emp) {
                        return 'Empresa #'.$h->empresa_id;
                    }
                    $codigo = trim((string) ($emp->codigo ?? ''));

                    return $codigo !== '' ? $codigo.' — '.$emp->nombre : (string) $emp->nombre;
                })->implode(', ');
            @endphp
            <div class="text-right">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-replicar-novedades-multiempresa"
                        title="Copia las novedades seleccionadas (o todas las no anuladas) al mismo legajo activo en otras empresas">
                    <i class="fa fa-copy"></i> Replicar a otras empresas
                </button>
                <div class="text-muted small mt-1" id="novedades-multiempresa-destino"
                     data-empresas="{{ e($empresasHermano) }}">
                    Activo tambi&eacute;n en: {{ $empresasHermano }}
                </div>
            </div>
        @endif
    </div>

    @if ($puedeEditar)
    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2">
            <strong id="novedad-empleado-form-titulo">Nueva novedad</strong>
        </div>
        <div class="card-body py-2">
            <form id="form-novedad-empleado" class="form-row align-items-end">
                <input type="hidden" name="novedad_id" value="">
                <div class="col-md-5 mb-2">
                    @include('sueldos.partials.campo_consulta_concepto_sueldos', [
                        'layout' => 'compact',
                        'label' => 'Concepto',
                        'inputName' => 'concepto_id',
                        'inputId' => 'novedad_empleado_concepto_id',
                        'conceptoId' => '',
                        'codigo' => '',
                        'descripcion' => '',
                        'required' => true,
                    ])
                </div>
                <div class="col-md-4 mb-2">
                    @include('sueldos.partials.campo_consulta_liquidacion_sueldos', [
                        'label' => 'Corrida',
                        'labelClass' => 'small mb-0 d-block',
                        'inputName' => 'liquidacion_id',
                        'inputId' => 'novedad_empleado_liquidacion_id',
                        'liquidacionId' => '',
                        'numero' => '',
                        'descripcion' => '',
                        'required' => false,
                        'empresaIdFiltro' => $empleado->empresa_id,
                    ])
                </div>
                <div class="form-group col-md-1 mb-2">
                    <label class="small mb-0" for="novedad_empleado_valor1">Valor 1</label>
                    <input type="number" step="0.0001" name="valor1" id="novedad_empleado_valor1" class="form-control form-control-sm" value="0">
                </div>
                <div class="form-group col-md-1 mb-2">
                    <label class="small mb-0" for="novedad_empleado_valor2">Valor 2</label>
                    <input type="number" step="0.0001" name="valor2" id="novedad_empleado_valor2" class="form-control form-control-sm" value="0">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0" for="novedad_empleado_estado">Estado</label>
                    <select name="estado" id="novedad_empleado_estado" class="form-control form-control-sm">
                        @foreach ($estados as $cod => $label)
                            <option value="{{ $cod }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0" for="novedad_empleado_fecha_vto">Fecha vto.</label>
                    <input type="date" name="fecha_vto" id="novedad_empleado_fecha_vto" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0" for="novedad_empleado_fecha_desde">Vigente desde</label>
                    <input type="date" name="fecha_desde" id="novedad_empleado_fecha_desde" class="form-control form-control-sm" title="Con desde se repite cada mes">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0" for="novedad_empleado_fecha_hasta">Vigente hasta</label>
                    <input type="date" name="fecha_hasta" id="novedad_empleado_fecha_hasta" class="form-control form-control-sm" title="Vacío = sin corte">
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label class="small mb-0" for="novedad_empleado_observacion">Observaci&oacute;n</label>
                    <input type="text" name="observacion" id="novedad_empleado_observacion" class="form-control form-control-sm" maxlength="500">
                </div>
                <div class="form-group col-md-3 mb-2 text-right">
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btn-novedad-empleado-cancelar">
                        <i class="fa fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    @if ($puedeReplicarMultiempresa)
                        <th style="width:36px" class="text-center">
                            <input type="checkbox" id="novedad-empleado-check-todas" title="Seleccionar todas las no anuladas">
                        </th>
                    @endif
                    <th>Corrida</th>
                    <th>Per&iacute;odo</th>
                    <th>Concepto</th>
                    <th class="text-right">V1</th>
                    <th class="text-right">V2</th>
                    <th>Estado</th>
                    <th>Vigencia</th>
                    <th>Origen</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($novedades as $n)
                    @php $esAnulada = (string) $n->estado === NovedadSueldosCatalogo::ESTADO_ANULADA; @endphp
                    <tr>
                        @if ($puedeReplicarMultiempresa)
                            <td class="text-center">
                                @if (! $esAnulada)
                                    <input type="checkbox" class="novedad-empleado-check" value="{{ $n->id }}">
                                @endif
                            </td>
                        @endif
                        <td>{{ optional($n->liquidacion)->numero }}</td>
                        <td>{{ $n->periodo }}</td>
                        <td>{{ optional($n->concepto)->codigo }} — {{ optional($n->concepto)->descripcion }}</td>
                        <td class="text-right">{{ number_format((float) $n->valor1, 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $n->valor2, 2, ',', '.') }}</td>
                        <td>{{ NovedadSueldosCatalogo::etiquetaEstado($n->estado) }}</td>
                        <td class="small">
                            @if ($n->fecha_desde)
                                {{ \Illuminate\Support\Carbon::parse($n->fecha_desde)->format('d/m/Y') }}
                                — {{ $n->fecha_hasta ? \Illuminate\Support\Carbon::parse($n->fecha_hasta)->format('d/m/Y') : '∞' }}
                            @else
                                <span class="text-muted">one-shot</span>
                            @endif
                        </td>
                        <td>{{ NovedadSueldosCatalogo::etiquetaOrigen($n->origen) }}</td>
                        <td class="text-nowrap">
                            @if ($puedeEditar)
                                <button type="button" class="btn-accion-tabla btn-novedad-empleado-editar"
                                        data-id="{{ $n->id }}"
                                        data-concepto-id="{{ $n->concepto_id }}"
                                        data-concepto-codigo="{{ optional($n->concepto)->codigo }}"
                                        data-concepto-desc="{{ optional($n->concepto)->descripcion }}"
                                        data-liquidacion-id="{{ $n->liquidacion_id }}"
                                        data-liquidacion-numero="{{ optional($n->liquidacion)->numero }}"
                                        data-liquidacion-desc="{{ trim((optional($n->liquidacion)->periodo ? optional($n->liquidacion)->periodo.' · ' : '').(optional($n->liquidacion)->descripcion ?? '')) }}"
                                        data-valor1="{{ $n->valor1 }}"
                                        data-valor2="{{ $n->valor2 }}"
                                        data-estado="{{ $n->estado }}"
                                        data-fecha-vto="{{ $n->fecha_vto ? \Illuminate\Support\Carbon::parse($n->fecha_vto)->format('Y-m-d') : '' }}"
                                        data-fecha-desde="{{ $n->fecha_desde ? \Illuminate\Support\Carbon::parse($n->fecha_desde)->format('Y-m-d') : '' }}"
                                        data-fecha-hasta="{{ $n->fecha_hasta ? \Illuminate\Support\Carbon::parse($n->fecha_hasta)->format('Y-m-d') : '' }}"
                                        data-observacion="{{ $n->observacion }}"
                                        title="Editar"><i class="fa fa-edit"></i></button>
                                <button type="button" class="btn-accion-tabla btn-novedad-empleado-borrar text-danger"
                                        data-id="{{ $n->id }}" title="Eliminar">
                                    <i class="fa fa-times-circle"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $puedeReplicarMultiempresa ? 10 : 9 }}" class="text-center text-muted py-3">Sin novedades para este legajo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
