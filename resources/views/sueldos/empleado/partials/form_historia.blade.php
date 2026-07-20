@php
    use App\Support\Sueldos\EmpleadoEstados;
    $ingresos = isset($data) ? ($data->ingresos ?? collect()) : collect();
    $puedeBaja = $puedeBaja ?? false;
@endphp

<div class="mb-3">
    <h5>Historia de ingresos / egresos</h5>
    <p class="text-muted small mb-2">Equivalente Anita <code>emping</code>: cada alta, baja y reincorporación queda registrada.</p>
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
                        <td>{{ ($ing->tipo_movimiento ?? '') === 'B' ? 'Baja' : 'Ingreso' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Sin historia registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($puedeBaja && isset($data))
    @if (! EmpleadoEstados::esBaja($data->estado))
        <div class="card card-outline card-danger mb-3">
            <div class="card-header"><strong>Dar de baja</strong></div>
            <div class="card-body">
                <form action="{{ route('baja_empleado_sueldos', ['id' => $data->id]) }}" method="POST" class="form-horizontal"
                      onsubmit="return confirm('¿Confirma la baja del empleado?');">
                    @csrf
                    <div class="form-group row">
                        <label class="col-lg-3 control-label requerido">Fecha de baja</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_egreso" class="form-control" required value="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Motivo</label>
                        <div class="col-lg-5">
                            <select name="motivoegreso_id" class="form-control">
                                <option value="">—</option>
                                @foreach ($motivosegreso ?? [] as $mot)
                                    <option value="{{ $mot->id }}">{{ $mot->codigo }} — {{ $mot->descripcion ?? $mot->nombre ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Comentario</label>
                        <div class="col-lg-5">
                            <input type="text" name="comentario_baja" class="form-control" maxlength="80">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger"><i class="fa fa-user-times"></i> Confirmar baja</button>
                </form>
            </div>
        </div>
    @else
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
    @endif
@endif
