@php
    $siguienteGrupoOr = $siguienteGrupoOr ?? 1;
    if ($siguienteGrupoOr < 1) {
        $siguienteGrupoOr = 1;
    }
@endphp
<div id="elegibilidad-concepto-panel" data-concepto="{{ $concepto->id }}"
     data-url="{{ route('elegibilidad_concepto_sueldos', ['concepto' => $concepto->id]) }}">

    <div class="card card-outline card-info mb-0">
        <div class="card-header py-2">
            <strong><i class="fa fa-filter"></i> Reglas de elegibilidad</strong>
            <span class="small text-muted ml-2">
                Mismo <em>grupo OR</em> = cualquiera alcanza; distintos grupos = deben cumplirse todos (AND).
                Vigencia opcional por fecha de liquidaci&oacute;n.
            </span>
        </div>
        <div class="card-body py-2">
            @if ($reglas->isNotEmpty())
            <table class="table table-sm table-bordered mb-3">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:70px;">Grupo OR</th>
                        <th>Campo</th>
                        <th>Operador</th>
                        <th>Valor</th>
                        <th>Vigencia</th>
                        <th style="width:70px;">Activo</th>
                        @if ($puedeEditar)<th style="width:50px;"></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reglas as $r)
                    <tr class="{{ $r->activo ? '' : 'text-muted' }}">
                        <td class="text-center"><span class="badge badge-dark">{{ $r->grupo_or ?? 1 }}</span></td>
                        <td>{{ $r->campoLabel() }}</td>
                        <td>{{ $r->operadorLabel() }}</td>
                        <td>{{ $r->valor ?? '—' }}</td>
                        <td class="small">{{ method_exists($r, 'vigenciaLabel') ? $r->vigenciaLabel() : '… – ∞' }}</td>
                        <td>{{ $r->activo ? 'Sí' : 'No' }}</td>
                        @if ($puedeEditar)
                        <td>
                            <button type="button" class="btn-accion-tabla text-danger btn-del-elegibilidad" data-id="{{ $r->id }}">
                                <i class="fa fa-times-circle"></i>
                            </button>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-muted small mb-3">Sin reglas: el concepto no se filtra por perfil del legajo (entra por grupo / cat&aacute;logo / novedad).</p>
            @endif

            @if ($puedeEditar)
            <form id="form-concepto-elegibilidad" class="form-row align-items-end">
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Grupo OR</label>
                    <input type="number" name="grupo_or" min="1" max="999" class="form-control form-control-sm"
                           value="{{ $siguienteGrupoOr }}"
                           title="Mismo n&uacute;mero = OR entre reglas; distinto = AND"/>
                </div>
                <div class="form-group col-md-3 mb-2">
                    <label class="small mb-0">Campo</label>
                    <select name="campo" class="form-control form-control-sm" required>
                        @foreach ($campos as $cod => $label)
                            <option value="{{ $cod }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Operador</label>
                    <select name="operador" id="eleg_operador" class="form-control form-control-sm" required>
                        @foreach ($operadores as $cod => $label)
                            <option value="{{ $cod }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Valor</label>
                    <input type="text" name="valor" id="eleg_valor" class="form-control form-control-sm"
                           placeholder="c&oacute;digo o lista 1,2,3"/>
                </div>
                <div class="form-group col-md-1 mb-2">
                    <label class="small mb-0">Desde</label>
                    <input type="date" name="vigente_desde" class="form-control form-control-sm"/>
                </div>
                <div class="form-group col-md-1 mb-2">
                    <label class="small mb-0">Hasta</label>
                    <input type="date" name="vigente_hasta" class="form-control form-control-sm"/>
                </div>
                <div class="form-group col-md-1 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                        <i class="fa fa-plus"></i>
                    </button>
                </div>
            </form>
            <p class="small text-muted mb-0 mt-1">
                Tip: para “sindicato 1 <strong>o</strong> 2”, us&aacute; el mismo Grupo OR en dos reglas (o un solo operador “En lista”).
                Dej&aacute; Grupo OR nuevo ({{ $siguienteGrupoOr }}) para AND con las anteriores.
            </p>
            @endif
        </div>
    </div>
</div>
