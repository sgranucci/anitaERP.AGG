<div id="planes-cuota-panel" data-empleado="{{ $empleado->id }}"
     data-url="{{ route('planes_cuota_empleado_sueldos', ['empleado' => $empleado->id]) }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h5 class="mb-0"><i class="fa fa-hand-holding-usd"></i> Préstamos / Cuotas</h5>
        <span class="text-muted small">Un concepto que se liquida <strong>N veces</strong> y cae solo al completarse. El contador avanza al <strong>cerrar</strong> la corrida.</span>
    </div>

    @if ($puedeEditar)
    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2">
            <strong id="plan-cuota-form-titulo">Nuevo plan de cuotas</strong>
        </div>
        <div class="card-body py-2">
            <form id="form-plan-cuota" class="form-row align-items-end">
                <input type="hidden" name="plan_id" value="">
                <div class="form-group col-md-4 mb-2">
                    <label class="small mb-0">Concepto con el que liquida <span class="text-danger">*</span></label>
                    <select name="concepto_id" class="form-control form-control-sm" required>
                        <option value="">— elegir —</option>
                        @foreach ($conceptos as $c)
                            <option value="{{ $c->id }}">{{ str_pad((string) $c->codigo, 4, '0', STR_PAD_LEFT) }} · {{ $c->descripcion }} ({{ $c->tipo }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label class="small mb-0">Descripción / leyenda <span class="text-danger">*</span></label>
                    <input type="text" name="descripcion" class="form-control form-control-sm" maxlength="120" placeholder="Ej.: Préstamo heladera" required>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Tipo de valor</label>
                    <select name="tipo_valor" id="plan-cuota-tipo-valor" class="form-control form-control-sm">
                        <option value="fijo">Importe fijo</option>
                        <option value="formula">Fórmula</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2" id="plan-cuota-wrap-valor">
                    <label class="small mb-0">Valor cuota</label>
                    <input type="number" step="0.01" name="cuota_valor" class="form-control form-control-sm" placeholder="0.00">
                </div>
                <div class="form-group col-md-6 mb-2 d-none" id="plan-cuota-wrap-formula">
                    <label class="small mb-0">Fórmula de la cuota</label>
                    <input type="text" name="cuota_formula" class="form-control form-control-sm" maxlength="2000" placeholder="ej.: empleado.sueldo_basico * 0.10">
                </div>

                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Cuotas totales (N) <span class="text-danger">*</span></label>
                    <input type="number" min="1" max="600" name="cuotas_totales" class="form-control form-control-sm" required>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">1ª cuota (mes) <span class="text-danger">*</span></label>
                    <input type="month" name="periodo_inicio_mes" class="form-control form-control-sm" required>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Importe total (opc.)</label>
                    <input type="number" step="0.01" name="importe_total" class="form-control form-control-sm" placeholder="0.00">
                </div>

                <div class="form-group col-md-6 mb-2">
                    <label class="small mb-0 d-block">Corridas donde descuenta</label>
                    @foreach ($tiposCorrida as $cod => $label)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="corridas_afecta[]" value="{{ $cod }}" id="cor-{{ $cod }}" {{ $cod === 'mensual' ? 'checked' : '' }}>
                            <label class="form-check-label small" for="cor-{{ $cod }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>

                <div class="form-group col-md-8 mb-2">
                    <label class="small mb-0">Observación</label>
                    <input type="text" name="observacion" class="form-control form-control-sm" maxlength="500">
                </div>
                <div class="form-group col-md-4 mb-2 text-right">
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btn-plan-cuota-cancelar-edicion"><i class="fa fa-times"></i> Cancelar edición</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i> Guardar plan</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover mb-0">
            <thead style="background-color:#85C1E9;color:#17202A;">
                <tr>
                    <th>Concepto</th>
                    <th>Descripción</th>
                    <th class="text-right">Cuota</th>
                    <th class="text-center">Avance</th>
                    <th class="text-center">1ª cuota</th>
                    <th class="text-right">Saldo est.</th>
                    <th class="text-center">Estado</th>
                    @if ($puedeEditar)<th style="width:140px"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($planes as $p)
                    @php
                        $liq = (int) $p->cuotas_liquidadas;
                        $tot = (int) $p->cuotas_totales;
                        $pct = $tot > 0 ? round($liq / $tot * 100) : 0;
                        $saldo = $p->importe_total !== null
                            ? max(0, (float) $p->importe_total - $liq * (float) ($p->cuota_valor ?? 0))
                            : null;
                        $badge = ['activa' => 'success', 'suspendida' => 'warning', 'finalizada' => 'secondary', 'cancelada' => 'danger'][$p->estado] ?? 'secondary';
                        $pini = (int) $p->periodo_inicio;
                    @endphp
                    <tr data-id="{{ $p->id }}" data-estado="{{ $p->estado }}"
                        data-concepto="{{ $p->concepto_id }}"
                        data-descripcion="{{ e($p->descripcion) }}"
                        data-tipo-valor="{{ $p->tipo_valor }}"
                        data-cuota-valor="{{ $p->cuota_valor }}"
                        data-cuota-formula="{{ e($p->cuota_formula) }}"
                        data-importe-total="{{ $p->importe_total }}"
                        data-cuotas-totales="{{ $tot }}"
                        data-periodo-inicio="{{ $pini }}"
                        data-corridas="{{ e(json_encode($p->corridasAfectadas())) }}"
                        data-observacion="{{ e($p->observacion) }}"
                        class="{{ in_array($p->estado, ['cancelada','finalizada'], true) ? 'text-muted' : '' }}">
                        <td class="small">{{ optional($p->concepto)->codigo ? str_pad((string) $p->concepto->codigo, 4, '0', STR_PAD_LEFT).' · '.$p->concepto->descripcion : '—' }}</td>
                        <td>{{ $p->descripcion }}</td>
                        <td class="text-right">
                            @if ($p->tipo_valor === 'formula')
                                <span class="badge badge-info" title="{{ $p->cuota_formula }}">fórmula</span>
                            @else
                                $ {{ number_format((float) $p->cuota_valor, 2, ',', '.') }}
                            @endif
                        </td>
                        <td class="text-center" style="min-width:120px">
                            <div class="small mb-1">{{ $liq }}/{{ $tot }}</div>
                            <div class="progress" style="height:6px">
                                <div class="progress-bar bg-{{ $badge }}" role="progressbar" style="width: {{ $pct }}%"></div>
                            </div>
                        </td>
                        <td class="text-center small">{{ $pini > 0 ? substr((string) $pini, 4, 2).'/'.substr((string) $pini, 0, 4) : '—' }}</td>
                        <td class="text-right">{{ $saldo !== null ? '$ '.number_format($saldo, 2, ',', '.') : '—' }}</td>
                        <td class="text-center"><span class="badge badge-{{ $badge }}">{{ $estados[$p->estado] ?? $p->estado }}</span></td>
                        @if ($puedeEditar)
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-link btn-sm p-0 text-primary btn-plan-cuota-editar" title="Editar"><i class="fa fa-edit"></i></button>
                            @if ($p->estado === 'activa')
                                <button type="button" class="btn btn-link btn-sm p-0 text-warning btn-plan-cuota-estado" data-accion="suspender" title="Suspender"><i class="fa fa-pause"></i></button>
                            @elseif ($p->estado === 'suspendida')
                                <button type="button" class="btn btn-link btn-sm p-0 text-success btn-plan-cuota-estado" data-accion="reactivar" title="Reactivar"><i class="fa fa-play"></i></button>
                            @endif
                            @if (in_array($p->estado, ['activa','suspendida'], true))
                                <button type="button" class="btn btn-link btn-sm p-0 text-danger btn-plan-cuota-estado" data-accion="cancelar" title="Cancelar (detener)"><i class="fa fa-ban"></i></button>
                            @endif
                            @if ($liq === 0)
                                <button type="button" class="btn btn-link btn-sm p-0 text-danger btn-plan-cuota-borrar" title="Eliminar"><i class="fa fa-trash"></i></button>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $puedeEditar ? 8 : 7 }}" class="text-center text-muted py-3">
                            Sin préstamos ni planes de cuotas cargados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
