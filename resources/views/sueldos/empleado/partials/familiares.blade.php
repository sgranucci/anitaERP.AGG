<div id="familiares-panel" data-empleado="{{ $empleado->id }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h5 class="mb-0"><i class="fa fa-users"></i> Familiares a cargo (Ganancias)</h5>
        <span class="text-muted small">Alimentan <code>cantidad("CONYUGE")</code>, <code>cantidad("HIJOS")</code>, etc. del plan.</span>
    </div>

    @if ($puedeEditar)
    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2"><strong>Agregar familiar</strong></div>
        <div class="card-body py-2">
            <form id="form-familiar-nuevo" class="form-row align-items-end">
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Tipo</label>
                    <select name="tipo" class="form-control form-control-sm" required>
                        @foreach ($tipos as $cod => $label)
                            <option value="{{ $cod }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Apellido</label>
                    <input type="text" name="apellido" class="form-control form-control-sm" maxlength="60">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Nombre</label>
                    <input type="text" name="nombre" class="form-control form-control-sm" maxlength="60">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Documento</label>
                    <input type="text" name="documento" class="form-control form-control-sm" maxlength="20">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Nacimiento</label>
                    <input type="date" name="fecha_nacimiento" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-1 mb-2">
                    <label class="small mb-0">% ded.</label>
                    <select name="porcentaje_deduccion" class="form-control form-control-sm">
                        <option value="100">100</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="form-group col-md-1 mb-2">
                    <button type="submit" class="btn btn-success btn-sm btn-block"><i class="fa fa-plus"></i></button>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Vigente desde</label>
                    <input type="date" name="vigente_desde" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Vigente hasta</label>
                    <input type="date" name="vigente_hasta" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label class="small mb-0">Observación</label>
                    <input type="text" name="observacion" class="form-control form-control-sm" maxlength="500">
                </div>
                <input type="hidden" name="activo" value="1">
            </form>
        </div>
    </div>
    @endif

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover mb-0">
            <thead style="background-color:#85C1E9;color:#17202A;">
                <tr>
                    <th>Tipo</th>
                    <th>Apellido y nombre</th>
                    <th>Documento</th>
                    <th>Nacimiento</th>
                    <th class="text-center">%</th>
                    <th>Vigencia</th>
                    <th class="text-center">Activo</th>
                    @if ($puedeEditar)<th style="width:90px"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($familiares as $f)
                    <tr data-id="{{ $f->id }}" data-tipo="{{ $f->tipo }}" data-activo="{{ $f->activo ? 1 : 0 }}" class="{{ $f->activo ? '' : 'text-muted' }}">
                        <td>{{ $f->tipo_descripcion }}</td>
                        <td>{{ trim(($f->apellido ?? '').' '.($f->nombre ?? '')) ?: '—' }}</td>
                        <td>{{ $f->documento ?: '—' }}</td>
                        <td>{{ optional($f->fecha_nacimiento)->format('d/m/Y') ?: '—' }}</td>
                        <td class="text-center">{{ $f->porcentaje_deduccion }}</td>
                        <td class="small">
                            {{ optional($f->vigente_desde)->format('d/m/Y') ?: '…' }}
                            —
                            {{ optional($f->vigente_hasta)->format('d/m/Y') ?: '…' }}
                        </td>
                        <td class="text-center">
                            @if ($f->activo)
                                <span class="badge badge-success">Sí</span>
                            @else
                                <span class="badge badge-secondary">No</span>
                            @endif
                        </td>
                        @if ($puedeEditar)
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-link btn-sm p-0 text-warning btn-familiar-toggle" title="{{ $f->activo ? 'Desactivar' : 'Activar' }}">
                                <i class="fa fa-{{ $f->activo ? 'eye-slash' : 'eye' }}"></i>
                            </button>
                            <button type="button" class="btn btn-link btn-sm p-0 text-danger btn-familiar-borrar" title="Eliminar">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $puedeEditar ? 8 : 7 }}" class="text-center text-muted py-3">
                            Sin familiares cargados. SiRADIG (F572) es independiente; aquí se definen las cantidades del plan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
