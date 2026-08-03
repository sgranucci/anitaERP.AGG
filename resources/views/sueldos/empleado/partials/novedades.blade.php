@php use App\Support\Sueldos\NovedadSueldosCatalogo; @endphp
<div id="novedades-empleado-panel" data-empleado="{{ $empleado->id }}"
     data-url="{{ route('novedades_empleado_sueldos', ['empleado' => $empleado->id]) }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h5 class="mb-0"><i class="fa fa-bolt"></i> Novedades del per&iacute;odo</h5>
        <span class="text-muted small">Datos de entrada que consume el motor (<code>novedad()</code> / <code>novedad2()</code>).</span>
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
                <div class="form-group col-md-4 mb-2">
                    <label class="small mb-0">Corrida</label>
                    <select name="liquidacion_id" class="form-control form-control-sm">
                        <option value="">— Sin corrida —</option>
                        @foreach ($liquidaciones as $liq)
                            <option value="{{ $liq->id }}">N&deg; {{ $liq->numero }} · {{ $liq->periodo }} · {{ $liq->descripcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-1 mb-2">
                    <label class="small mb-0">Valor 1</label>
                    <input type="number" step="0.0001" name="valor1" class="form-control form-control-sm" value="0">
                </div>
                <div class="form-group col-md-1 mb-2">
                    <label class="small mb-0">Valor 2</label>
                    <input type="number" step="0.0001" name="valor2" class="form-control form-control-sm" value="0">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Estado</label>
                    <select name="estado" class="form-control form-control-sm">
                        @foreach ($estados as $cod => $label)
                            <option value="{{ $cod }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Fecha vto.</label>
                    <input type="date" name="fecha_vto" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Vigente desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm" title="Con desde se repite cada mes">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Vigente hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" title="Vacío = sin corte">
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label class="small mb-0">Observaci&oacute;n</label>
                    <input type="text" name="observacion" class="form-control form-control-sm" maxlength="500">
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
                    <tr>
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
                        <td colspan="9" class="text-center text-muted py-3">Sin novedades para este legajo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
