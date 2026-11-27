@php
    use App\Support\Sueldos\EmpleadoBajaSupport;
    use App\Support\Sueldos\EmpleadoEstados;
    $ingresos = isset($data) ? ($data->ingresos ?? collect()) : collect();
    $puedeBaja = $puedeBaja ?? false;
    $puedeBorrarHistoria = $puedeBorrarHistoria ?? false;
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
                    @if ($puedeBorrarHistoria)
                        <th class="width80"></th>
                    @endif
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
                        @if ($puedeBorrarHistoria)
                            <td class="text-nowrap text-center">
                                <form action="{{ route('eliminar_historia_empleado_sueldos', ['id' => $data->id, 'ingresoId' => $ing->id]) }}"
                                      method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar este registro de historia?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-accion-tabla tooltipsC text-danger" title="Eliminar registro">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $puedeBorrarHistoria ? 6 : 5 }}" class="text-center text-muted">Sin historia registrada.</td></tr>
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
                        <div class="col-lg-9 offset-lg-3">
                            <div class="custom-control custom-radio">
                                <input type="radio" class="custom-control-input" id="reinc-modo-reabrir" name="modo_reincorporacion" value="reabrir">
                                <label class="custom-control-label" for="reinc-modo-reabrir">
                                    Reabrir el último período (mismo legajo, se quita egreso y motivo; no crea un ingreso nuevo)
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" class="custom-control-input" id="reinc-modo-nuevo" name="modo_reincorporacion" value="nuevo" checked>
                                <label class="custom-control-label" for="reinc-modo-nuevo">Nuevo ingreso con fecha</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row" id="reinc-fila-fecha">
                        <label class="col-lg-3 control-label text-right pr-2">Nueva fecha de ingreso</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_ingreso" id="reinc-fecha-ingreso" class="form-control" value="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>
                    <script>
                        (function () {
                            var radios = document.querySelectorAll('input[name="modo_reincorporacion"]');
                            var fecha = document.getElementById('reinc-fecha-ingreso');
                            var fila = document.getElementById('reinc-fila-fecha');
                            function sync() {
                                var reabrir = document.getElementById('reinc-modo-reabrir').checked;
                                fila.style.display = reabrir ? 'none' : '';
                                fecha.required = !reabrir;
                            }
                            radios.forEach(function (r) { r.addEventListener('change', sync); });
                            sync();
                        })();
                    </script>
                    <button type="submit" class="btn btn-success"><i class="fa fa-user-plus"></i> Reincorporar</button>
                </form>
            </div>
        </div>
    @elseif ($esProvisoria)
        @php
            $motBpId = (int) old('motivoegreso_id', $data->motivoegreso_id ?? 0);
            $motBp = $motBpId > 0
                ? (optional($data->motivoegreso)->id === $motBpId
                    ? $data->motivoegreso
                    : \App\Models\Sueldos\Motivoegreso_Sueldos::query()->find($motBpId))
                : null;
        @endphp
        <div class="card card-outline card-info mb-3">
            <div class="card-header"><strong>Modificar baja provisoria</strong></div>
            <div class="card-body">
                <p class="small text-muted">Corregí la fecha (o el motivo) si se cargó mal. El legajo sigue activo hasta confirmar la definitiva.</p>
                <form action="{{ route('modificar_baja_provisoria_empleado_sueldos', ['id' => $data->id]) }}" method="POST" class="form-horizontal"
                      onsubmit="return confirm('¿Guardar los cambios de la baja provisoria?');">
                    @csrf
                    <div class="form-group row">
                        <label class="col-lg-2 control-label requerido">Fecha de baja</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_egreso" class="form-control" required
                                   value="{{ old('fecha_egreso', optional($data->fecha_egreso)->format('Y-m-d') ?: now()->format('Y-m-d')) }}">
                        </div>
                    </div>
                    @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                        'maestro' => 'motivoegreso',
                        'registroId' => $motBp->id ?? '',
                        'codigo' => $motBp->codigo ?? '',
                        'descripcion' => $motBp->descripcion ?? '',
                        'label' => 'Motivo',
                        'inputName' => 'motivoegreso_id',
                        'inputId' => 'motivoegreso_id_mod_bp',
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-6',
                    ])
                    <div class="form-group row">
                        <label class="col-lg-2 control-label">Comentario</label>
                        <div class="col-lg-6">
                            <input type="text" name="comentario_baja" class="form-control" maxlength="80"
                                   value="{{ old('comentario_baja', $data->comentario_baja ?? '') }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-info"><i class="fa fa-edit"></i> Guardar cambios</button>
                </form>
            </div>
        </div>
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
                            @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                                'maestro' => 'motivoegreso',
                                'registroId' => $motBp->id ?? '',
                                'codigo' => $motBp->codigo ?? '',
                                'descripcion' => $motBp->descripcion ?? '',
                                'label' => 'Motivo',
                                'inputName' => 'motivoegreso_id',
                                'inputId' => 'motivoegreso_id_conf',
                                'col_label' => 'col-lg-4',
                                'col_input' => 'col-lg-7',
                            ])
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
                            @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                                'maestro' => 'motivoegreso',
                                'registroId' => '',
                                'codigo' => '',
                                'descripcion' => '',
                                'label' => 'Motivo',
                                'inputName' => 'motivoegreso_id',
                                'inputId' => 'motivoegreso_id_bp',
                                'col_label' => 'col-lg-4',
                                'col_input' => 'col-lg-7',
                            ])
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
                            @include('sueldos.partials.campo_consulta_maestro_sueldos', [
                                'maestro' => 'motivoegreso',
                                'registroId' => '',
                                'codigo' => '',
                                'descripcion' => '',
                                'label' => 'Motivo',
                                'inputName' => 'motivoegreso_id',
                                'inputId' => 'motivoegreso_id_bd',
                                'col_label' => 'col-lg-4',
                                'col_input' => 'col-lg-7',
                            ])
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
