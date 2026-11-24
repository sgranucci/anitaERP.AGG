@php
    use App\Support\Sueldos\EmpleadoBajaSupport;
    use App\Support\Sueldos\EmpleadoEstados;
    $ingresos = isset($data) ? ($data->ingresos ?? collect()) : collect();
    $puedeBaja = $puedeBaja ?? false;
    $esProvisoria = isset($data) && EmpleadoBajaSupport::esBajaProvisoria($data);
    $esDefinitiva = isset($data) && EmpleadoBajaSupport::esBajaDefinitiva($data);
@endphp

<div class="mb-3">
    <h5>Historia de ingresos / egresos</h5>
    <p class="text-muted small mb-2">Equivalente Anita <code>emping</code>: cada alta, baja provisoria, baja definitiva y reincorporación queda registrada.</p>
    @if ($esProvisoria)
        <div class="alert alert-warning py-2 small">
            <i class="fa fa-clock"></i>
            <strong>Baja provisoria</strong> programada para
            {{ optional($data->fecha_egreso)->format('d/m/Y') }}.
            El legajo sigue <strong>activo</strong> en liquidaciones mensuales hasta confirmar la baja definitiva.
        </div>
    @endif
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead style="background-color:#85C1E9;color:#17202A;">
                <tr>
                    <th>Fecha ingreso</th>
                    <th>Fecha egreso</th>
                    <th>Motivo</th>
                    <th>Comentario</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ingresos as $ing)
                    <tr>
                        <td>{{ optional($ing->fecha_ingreso)->format('d/m/Y') }}</td>
                        <td>{{ optional($ing->fecha_egreso)->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ optional($ing->motivoegreso)->descripcion ?? optional($ing->motivoegreso)->nombre ?? '—' }}</td>
                        <td>{{ $ing->comentario_baja ?: '—' }}</td>
                        <td>{{ EmpleadoBajaSupport::labelMovimiento($ing->tipo_movimiento ?? '') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Sin historia registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($puedeBaja && isset($data))
    @if ($esDefinitiva)
        <div class="card card-outline card-success mb-3">
            <div class="card-header"><strong>Reincorporar</strong></div>
            <div class="card-body">
                <form action="{{ route('reincorporar_empleado_sueldos', ['id' => $data->id]) }}" method="POST"
                      onsubmit="return confirm('¿Confirma la reincorporación?');">
                    @csrf
                    <div class="form-group row">
                        <label class="col-lg-3 control-label requerido">Nueva fecha de ingreso</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_ingreso" class="form-control" required value="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fa fa-user-plus"></i> Reincorporar</button>
                </form>
            </div>
        </div>
    @elseif ($esProvisoria)
        <div class="row">
            <div class="col-md-6">
                <div class="card card-outline card-warning mb-3">
                    <div class="card-header"><strong>Confirmar baja definitiva</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">Pasa el legajo a Baja y lo saca de la nómina mensual. Entra en liquidación final del período de egreso.</p>
                        <form action="{{ route('baja_definitiva_empleado_sueldos', ['id' => $data->id]) }}" method="POST" class="form-horizontal"
                              onsubmit="return confirm('¿Confirma la baja definitiva?');">
                            @csrf
                            <div class="form-group row">
                                <label class="col-lg-4 control-label requerido">Fecha de baja</label>
                                <div class="col-lg-7">
                                    <input type="date" name="fecha_egreso" class="form-control" required
                                           value="{{ optional($data->fecha_egreso)->format('Y-m-d') ?: now()->format('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-lg-4 control-label">Motivo</label>
                                <div class="col-lg-7">
                                    <select name="motivoegreso_id" class="form-control">
                                        <option value="">—</option>
                                        @foreach ($motivosegreso ?? [] as $mot)
                                            <option value="{{ $mot->id }}" @selected((int)($data->motivoegreso_id ?? 0) === (int)$mot->id)>
                                                {{ $mot->codigo }} — {{ $mot->descripcion ?? $mot->nombre ?? '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-lg-4 control-label">Comentario</label>
                                <div class="col-lg-7">
                                    <input type="text" name="comentario_baja" class="form-control" maxlength="80"
                                           value="{{ $data->comentario_baja ?? '' }}">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-danger"><i class="fa fa-user-times"></i> Confirmar definitiva</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header"><strong>Anular baja provisoria</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">Quita la fecha de egreso programada. El legajo queda activo sin egreso.</p>
                        <form action="{{ route('anular_baja_provisoria_empleado_sueldos', ['id' => $data->id]) }}" method="POST"
                              onsubmit="return confirm('¿Anular la baja provisoria?');">
                            @csrf
                            <div class="form-group">
                                <label class="small">Comentario (opcional)</label>
                                <input type="text" name="comentario" class="form-control form-control-sm" maxlength="80">
                            </div>
                            <button type="submit" class="btn btn-outline-secondary"><i class="fa fa-undo"></i> Anular provisoria</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-md-6">
                <div class="card card-outline card-warning mb-3">
                    <div class="card-header"><strong>Baja provisoria</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">
                            Como en Anita: carga la fecha de egreso y el legajo <strong>sigue activo</strong> en liquidaciones
                            hasta confirmar la baja definitiva. Se avisa a Lorena.
                        </p>
                        <form action="{{ route('baja_provisoria_empleado_sueldos', ['id' => $data->id]) }}" method="POST" class="form-horizontal"
                              onsubmit="return confirm('¿Programar baja provisoria? El empleado seguirá activo hasta confirmar la definitiva.');">
                            @csrf
                            <div class="form-group row">
                                <label class="col-lg-4 control-label requerido">Fecha de baja</label>
                                <div class="col-lg-7">
                                    <input type="date" name="fecha_egreso" class="form-control" required value="{{ now()->format('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-lg-4 control-label">Motivo</label>
                                <div class="col-lg-7">
                                    <select name="motivoegreso_id" class="form-control">
                                        <option value="">—</option>
                                        @foreach ($motivosegreso ?? [] as $mot)
                                            <option value="{{ $mot->id }}">{{ $mot->codigo }} — {{ $mot->descripcion ?? $mot->nombre ?? '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-lg-4 control-label">Comentario</label>
                                <div class="col-lg-7">
                                    <input type="text" name="comentario_baja" class="form-control" maxlength="80">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning"><i class="fa fa-clock"></i> Programar baja provisoria</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-outline card-danger mb-3">
                    <div class="card-header"><strong>Baja definitiva</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">
                            Sale de la nómina mensual de inmediato. Entra en liquidación final del período de egreso.
                        </p>
                        <form action="{{ route('baja_definitiva_empleado_sueldos', ['id' => $data->id]) }}" method="POST" class="form-horizontal"
                              onsubmit="return confirm('¿Confirma la baja definitiva del empleado?');">
                            @csrf
                            <div class="form-group row">
                                <label class="col-lg-4 control-label requerido">Fecha de baja</label>
                                <div class="col-lg-7">
                                    <input type="date" name="fecha_egreso" class="form-control" required value="{{ now()->format('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-lg-4 control-label">Motivo</label>
                                <div class="col-lg-7">
                                    <select name="motivoegreso_id" class="form-control">
                                        <option value="">—</option>
                                        @foreach ($motivosegreso ?? [] as $mot)
                                            <option value="{{ $mot->id }}">{{ $mot->codigo }} — {{ $mot->descripcion ?? $mot->nombre ?? '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-lg-4 control-label">Comentario</label>
                                <div class="col-lg-7">
                                    <input type="text" name="comentario_baja" class="form-control" maxlength="80">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-danger"><i class="fa fa-user-times"></i> Confirmar baja definitiva</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
