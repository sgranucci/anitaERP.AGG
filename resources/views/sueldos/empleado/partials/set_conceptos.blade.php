@php use App\Support\Sueldos\ConceptoElegibilidadCatalogo; @endphp
<div id="set-conceptos-panel" data-empleado="{{ $empleado->id }}"
     data-url="{{ route('set_conceptos_empleado_sueldos', ['empleado' => $empleado->id]) }}">

    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2">
            <strong><i class="fa fa-layer-group"></i> Grupos de conceptos</strong>
            @php
                $modoSet = $set['modo'] ?? '';
                $modoLabel = $set['modo_label'] ?? ConceptoElegibilidadCatalogo::modoLabel($modoSet);
                $badgeModo = $modoSet === ConceptoElegibilidadCatalogo::MODO_GRUPOS ? 'success' : 'warning';
            @endphp
            <span class="badge badge-{{ $badgeModo }} ml-1">{{ $modoLabel }}</span>
            <span class="small text-muted ml-2">N sin l&iacute;mite (Anita trae 3; ac&aacute; se pueden agregar m&aacute;s)</span>
        </div>
        <div class="card-body py-2">
            <table class="table table-sm table-bordered mb-2">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>Grupo</th>
                        <th style="width:100px;">Origen</th>
                        <th style="width:90px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($set['grupos'] as $g)
                        <tr>
                            <td>{{ $g['orden'] }}</td>
                            <td>
                                @if (($g['id'] ?? 0) > 0 && ($puedeConsultarGrupo ?? false))
                                    <a href="{{ route('editar_grupo_concepto_sueldos', ['id' => $g['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                       class="text-primary" target="_blank" rel="noopener"
                                       title="Consultar grupo">{{ $g['codigo'] }} — {{ $g['descripcion'] }}</a>
                                @else
                                    {{ $g['codigo'] }} — {{ $g['descripcion'] }}
                                @endif
                            </td>
                            <td class="small">
                                <span class="badge badge-{{ ($g['origen'] ?? '') === 'sync_anita' ? 'info' : 'secondary' }}">
                                    {{ ($g['origen'] ?? '') === 'sync_anita' ? 'Anita' : 'Manual' }}
                                </span>
                            </td>
                            <td class="text-nowrap">
                                @if (($g['id'] ?? 0) > 0 && ($puedeConsultarGrupo ?? false))
                                    <a href="{{ route('editar_grupo_concepto_sueldos', ['id' => $g['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                       class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener"
                                       title="Consultar grupo">
                                        <i class="fa fa-search"></i>
                                    </a>
                                @endif
                                @if ($puedeEditar && ($g['pivot_id'] ?? 0) > 0)
                                    <button type="button" class="btn-accion-tabla text-danger btn-del-grupo-concepto"
                                            data-id="{{ $g['pivot_id'] }}" title="Quitar grupo">
                                        <i class="fa fa-times-circle"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted text-center">
                                Sin grupos: se usa el cat&aacute;logo activo filtrado por elegibilidad de cada concepto (estilo SAP).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($puedeEditar)
            <form id="form-empleado-agregar-grupo" class="form-row align-items-end">
                <div class="form-group col-md-9 mb-0">
                    <label class="small mb-0">Agregar grupo</label>
                    <select name="grupo_concepto_id" class="form-control form-control-sm" required>
                        <option value="">— Elegir grupo —</option>
                        @foreach ($gruposDisponibles as $g)
                            @if (! in_array((int) $g->id, $asignadosIds ?? [], true))
                                <option value="{{ $g->id }}">{{ $g->codigo }} — {{ $g->descripcion }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3 mb-0">
                    <button type="submit" class="btn btn-success btn-sm btn-block">
                        <i class="fa fa-plus"></i> Agregar
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>

    @if ($puedeEditar)
    <div class="card card-outline card-primary mb-3">
        <div class="card-header py-2"><strong>Asignaci&oacute;n expl&iacute;cita (+/-)</strong></div>
        <div class="card-body py-2">
            <form id="form-empleado-concepto-explicito" class="form-row align-items-end">
                <div class="col-md-5 mb-2">
                    @include('sueldos.partials.campo_consulta_concepto_sueldos', [
                        'layout' => 'compact',
                        'label' => 'Concepto',
                        'inputName' => 'concepto_id',
                        'inputId' => 'explicito_concepto_id',
                        'conceptoId' => '',
                        'codigo' => '',
                        'descripcion' => '',
                        'required' => true,
                    ])
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Acción</label>
                    <select name="accion" class="form-control form-control-sm">
                        @foreach ($acciones as $cod => $label)
                            <option value="{{ $cod }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small mb-0">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-1 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-plus"></i></button>
                </div>
            </form>
            @if ($explicatos->isNotEmpty())
            <table class="table table-sm table-bordered mt-2 mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr><th>Concepto</th><th>Acción</th><th>Vigencia</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($explicatos as $ex)
                    <tr>
                        <td>{{ optional($ex->concepto)->codigo }} — {{ optional($ex->concepto)->descripcion }}</td>
                        <td>{{ $ex->accionLabel() }}</td>
                        <td class="small">
                            {{ $ex->fecha_desde ? $ex->fecha_desde->format('d/m/Y') : '…' }}
                            — {{ $ex->fecha_hasta ? $ex->fecha_hasta->format('d/m/Y') : '∞' }}
                        </td>
                        <td>
                            <button type="button" class="btn-accion-tabla text-danger btn-del-explicito" data-id="{{ $ex->id }}">
                                <i class="fa fa-times-circle"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @endif

    <div class="card card-outline card-info mb-3">
        <div class="card-header py-2 d-flex justify-content-between">
            <strong>Set efectivo</strong>
            <span class="small text-muted">{{ $set['conceptos']->count() }} conceptos · {{ count($set['excluidos'] ?? []) }} excluidos</span>
        </div>
        <div class="card-body p-0" style="max-height: 220px; overflow-y: auto;">
            <table class="table table-sm table-striped mb-0">
                <thead style="background:#85C1E9;color:#17202A; position:sticky; top:0;">
                    <tr><th>Cód.</th><th>Concepto</th><th>Origen</th></tr>
                </thead>
                <tbody>
                    @forelse ($set['conceptos'] as $c)
                        @php
                            $m = $set['meta'][$c->id] ?? [];
                            $origen = $m['origen'] ?? '?';
                            $label = $m['origen_label'] ?? ConceptoElegibilidadCatalogo::origenLabel($origen);
                            $badge = $m['origen_badge'] ?? ConceptoElegibilidadCatalogo::origenBadge($origen);
                        @endphp
                        <tr>
                            <td>{{ $c->codigo }}</td>
                            <td>
                                {{ $c->descripcion }}
                                @if (! empty($m['detalle']))
                                    <div class="small text-muted">{{ $m['detalle'] }}</div>
                                @endif
                            </td>
                            <td class="small" title="{{ $m['detalle'] ?? '' }}">
                                <span class="badge badge-{{ $badge }}">{{ $label }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">Set vacío</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-outline card-warning mb-0">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong><i class="fa fa-ban"></i> Por qu&eacute; no entraron</strong>
            <span class="small text-muted">{{ count($set['excluidos'] ?? []) }} excluidos</span>
        </div>
        <div class="card-body p-0" style="max-height: 200px; overflow-y: auto;">
            <table class="table table-sm table-striped mb-0">
                <thead style="background:#85C1E9;color:#17202A; position:sticky; top:0;">
                    <tr>
                        <th style="width:70px;">Cód.</th>
                        <th style="width:28%;">Concepto</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($set['excluidos'] ?? []) as $ex)
                        <tr>
                            <td>{{ $ex['codigo'] ?? '' }}</td>
                            <td>{{ $ex['descripcion'] ?? '' }}</td>
                            <td class="small">{{ $ex['motivo'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">
                                Ning&uacute;n candidato fue excluido por elegibilidad o expl&iacute;cito.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
