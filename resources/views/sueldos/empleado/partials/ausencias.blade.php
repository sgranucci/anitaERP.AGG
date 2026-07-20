@php
    use App\Models\Sueldos\Empleado_Ausencia_Sueldos;
    $estados = Empleado_Ausencia_Sueldos::ESTADOS;
    $anioActual = (int) date('Y');
    $badgeEstado = [
        'planificada' => 'secondary',
        'aprobada' => 'info',
        'tomada' => 'primary',
        'liquidada' => 'success',
        'anulada' => 'danger',
    ];
@endphp
<div id="ausencias-panel"
     data-empleado="{{ $empleado->id }}"
     data-url-guardar="{{ route('guardar_ausencia_empleado_sueldos', ['empleado' => $empleado->id]) }}"
     data-url-devengar="{{ route('devengar_ausencia_empleado_sueldos', ['empleado' => $empleado->id]) }}">

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h5 class="mb-0"><i class="fa fa-umbrella-beach"></i> Vacaciones, licencias y ausencias</h5>
        @if ($puedeEditar)
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-devengar-ausencias">
                <i class="fa fa-sync"></i> Recalcular saldos
            </button>
        @endif
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($resumen['saldo'], 2, ',', '.') }}</h3>
                    <p>Saldo total de vacaciones (días)</p>
                </div>
                <div class="icon"><i class="fa fa-umbrella-beach"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($resumen['devengado'], 2, ',', '.') }}</h3>
                    <p>Devengado acumulado</p>
                </div>
                <div class="icon"><i class="fa fa-plus-circle"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ number_format($resumen['consumido'], 2, ',', '.') }}</h3>
                    <p>Consumido</p>
                </div>
                <div class="icon"><i class="fa fa-minus-circle"></i></div>
            </div>
        </div>
    </div>

    <h6 class="text-muted">Saldo por período</h6>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered">
            <thead style="background-color:#85C1E9; color:#17202A;">
                <tr>
                    <th>Período</th>
                    <th class="text-right">Devengado</th>
                    <th class="text-right">Consumido</th>
                    <th class="text-right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($resumen['periodos'] as $p)
                    <tr>
                        <td>{{ $p['anio'] }}</td>
                        <td class="text-right">{{ number_format($p['devengado'], 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($p['consumido'], 2, ',', '.') }}</td>
                        <td class="text-right font-weight-bold {{ $p['saldo'] < 0 ? 'text-danger' : '' }}">{{ number_format($p['saldo'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            Sin movimientos. Cargue la fecha de ingreso y pulse «Recalcular saldos».
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h6 class="text-muted">Eventos registrados</h6>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered table-hover">
            <thead style="background-color:#85C1E9; color:#17202A;">
                <tr>
                    <th>Tipo</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th class="text-right">Días</th>
                    <th>Imputa</th>
                    <th>Estado</th>
                    <th>Observación</th>
                    <th style="width:70px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ausencias as $a)
                    @php
                        $ausenciaData = [
                            'id' => $a->id,
                            'tipo_ausencia_id' => $a->tipo_ausencia_id,
                            'anio_imputacion' => $a->anio_imputacion,
                            'fecha_desde' => optional($a->fecha_desde)->format('Y-m-d'),
                            'fecha_hasta' => optional($a->fecha_hasta)->format('Y-m-d'),
                            'dias' => (float) $a->dias,
                            'tipo_dias' => $a->tipo_dias,
                            'estado' => $a->estado,
                            'observacion' => $a->observacion,
                            'url' => route('actualizar_ausencia_empleado_sueldos', ['id' => $a->id]),
                        ];
                    @endphp
                    <tr>
                        <td>{{ $a->tipo->nombre ?? '—' }}</td>
                        <td>{{ optional($a->fecha_desde)->format('d/m/Y') }}</td>
                        <td>{{ optional($a->fecha_hasta)->format('d/m/Y') }}</td>
                        <td class="text-right">{{ number_format((float) $a->dias, 2, ',', '.') }}</td>
                        <td>{{ $a->anio_imputacion ?? '—' }}</td>
                        <td><span class="badge badge-{{ $badgeEstado[$a->estado] ?? 'secondary' }}">{{ $estados[$a->estado] ?? $a->estado }}</span></td>
                        <td>{{ $a->observacion }}</td>
                        <td class="text-nowrap">
                            @if ($puedeEditar)
                                <button type="button" class="btn-accion-tabla btn-editar-ausencia tooltipsC" title="Editar"
                                        data-ausencia='@json($ausenciaData)'>
                                    <i class="fa fa-edit"></i>
                                </button>
                                <button type="button" class="btn-accion-tabla btn-eliminar-ausencia tooltipsC" title="Eliminar"
                                        data-url="{{ route('eliminar_ausencia_empleado_sueldos', ['id' => $a->id]) }}">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">Sin eventos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($puedeEditar)
        <div class="card card-outline card-primary">
            <div class="card-header py-2">
                <h6 class="card-title mb-0" id="ausencia-form-titulo">Registrar ausencia</h6>
            </div>
            <div class="card-body">
                <form id="form-ausencia" data-url-crear="{{ route('guardar_ausencia_empleado_sueldos', ['empleado' => $empleado->id]) }}">
                    <input type="hidden" id="ausencia_id" value="">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="small mb-1">Tipo de ausencia</label>
                            <select id="ausencia_tipo" class="form-control form-control-sm" required
                                    data-tipos='@json($tipos->map(fn ($t) => ["id" => $t->id, "tipo_dias" => $t->tipo_dias, "vacaciones" => $t->esVacaciones()])->values())'>
                                @foreach ($tipos as $t)
                                    <option value="{{ $t->id }}" data-tipo-dias="{{ $t->tipo_dias }}" data-vacaciones="{{ $t->esVacaciones() ? 1 : 0 }}">{{ $t->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Desde</label>
                            <input type="date" id="ausencia_desde" class="form-control form-control-sm" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Hasta</label>
                            <input type="date" id="ausencia_hasta" class="form-control form-control-sm" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Tipo de días</label>
                            <select id="ausencia_tipo_dias" class="form-control form-control-sm">
                                <option value="corridos">Corridos</option>
                                <option value="habiles">Hábiles</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Días (auto)</label>
                            <input type="number" step="0.5" min="0" id="ausencia_dias" class="form-control form-control-sm" placeholder="Auto">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Imputa período</label>
                            <input type="number" id="ausencia_anio" class="form-control form-control-sm" min="1990" max="2100" placeholder="{{ $anioActual }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Estado</label>
                            <select id="ausencia_estado" class="form-control form-control-sm">
                                @foreach ($estados as $val => $label)
                                    <option value="{{ $val }}" {{ $val === 'tomada' ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-7">
                            <label class="small mb-1">Observación</label>
                            <input type="text" id="ausencia_obs" class="form-control form-control-sm" maxlength="255">
                        </div>
                    </div>
                    <div class="text-right">
                        <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btn-cancelar-ausencia">Cancelar edición</button>
                        <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i> Guardar ausencia</button>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Solo los estados <strong>Tomada</strong> y <strong>Liquidada</strong> descuentan del saldo de vacaciones. El período de imputación permite cargar vacaciones fraccionadas contra un mismo año.</p>
                </form>
            </div>
        </div>
    @endif
</div>
