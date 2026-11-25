@php
    $etapasErrorLiq = \App\Models\Sueldos\Liquidacion_Error_Sueldos::ETAPAS;
    $etapaBadge = [
        'formula' => 'danger',
        'empleado' => 'danger',
        'recibo' => 'warning',
        'omitido' => 'secondary',
    ];
    $conceptosUnicos = $conceptosUnicosConError ?? collect();
    $puedeEditarConc = ! empty($puedeEditarConcepto);
@endphp
@if (($erroresCountTotal ?? 0) > 0)
    <div class="card card-outline card-danger mb-3" id="panel-errores-liquidacion">
        <div class="card-header py-2">
            <h3 class="card-title mb-0">
                <i class="fa fa-exclamation-triangle text-danger"></i>
                Errores de liquidaci&oacute;n
                <span class="badge badge-danger">{{ $erroresCountTotal }}</span>
            </h3>
            <div class="card-tools">
                @foreach ($etapasErrorLiq as $cod => $label)
                    @php $c = (int) ($erroresCountPorEtapa[$cod] ?? 0); @endphp
                    @if ($c > 0)
                        <span class="badge badge-{{ $etapaBadge[$cod] ?? 'secondary' }} mr-1">{{ $label }}: {{ $c }}</span>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="card-body py-2">
            <p class="small text-muted mb-2">
                Se regeneran en cada <strong>Calcular / Recalcular</strong>.
                Los de f&oacute;rmula dejan el concepto en importe 0 y siguen;
                los de empleado/recibo omiten ese legajo sin abortar el resto de la corrida.
            </p>

            @if ($conceptosUnicos->isNotEmpty())
                <div class="alert alert-light border mb-3 py-2">
                    <div class="font-weight-bold mb-1">
                        Conceptos a corregir
                        <span class="badge badge-secondary">{{ $conceptosUnicos->count() }}</span>
                        <small class="text-muted font-weight-normal">(una sola edici&oacute;n corrige todos los legajos afectados)</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 bg-white">
                            <thead>
                                <tr class="text-muted">
                                    <th style="width:70px">C&oacute;d.</th>
                                    <th>Concepto</th>
                                    <th style="width:70px">Legajos</th>
                                    <th>Error</th>
                                    <th style="width:220px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($conceptosUnicos as $cu)
                                    @php
                                        $editParams = array_filter([
                                            'resaltar_pos' => $cu['formula_pos'],
                                            'campo' => $cu['formula_campo'] ?? 'formula',
                                            'origen' => 'liquidacion',
                                            'liquidacion_id' => $liq->id,
                                        ], fn ($v) => $v !== null && $v !== '');
                                    @endphp
                                    <tr>
                                        <td>{{ $cu['concepto_codigo'] }}</td>
                                        <td>{{ $cu['concepto_descripcion'] ?: '—' }}</td>
                                        <td>{{ $cu['cantidad'] }}</td>
                                        <td class="small" style="word-break:break-word;">{{ $cu['mensaje'] }}</td>
                                        <td class="text-nowrap">
                                            @if ($puedeEditarConc && ! empty($cu['concepto_id']))
                                                <a href="{{ route('editar_concepto_sueldos', ['id' => $cu['concepto_id']] + $editParams) }}"
                                                   class="btn btn-sm btn-danger"
                                                   target="_blank" rel="noopener"
                                                   title="Abrir el concepto y marcar la posición del error en la fórmula">
                                                    <i class="fa fa-edit"></i> Editar f&oacute;rmula
                                                </a>
                                            @elseif (! empty($cu['concepto_id']))
                                                <a href="{{ route('editar_concepto_sueldos', ['id' => $cu['concepto_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta'] + $editParams) }}"
                                                   class="btn btn-sm btn-outline-danger"
                                                   target="_blank" rel="noopener">
                                                    <i class="fa fa-eye"></i> Ver f&oacute;rmula
                                                </a>
                                            @else
                                                <span class="text-muted small">Sin ID de concepto</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <form method="get" action="{{ route('resultado_liquidacion_sueldos', ['id' => $liq->id]) }}"
                  class="form-inline mb-2 flex-wrap" id="form-filtrar-errores-liq">
                @if (request()->filled('q'))
                    <input type="hidden" name="q" value="{{ request('q') }}">
                @endif
                <label class="sr-only" for="err_etapa">Etapa</label>
                <select name="err_etapa" id="err_etapa" class="form-control form-control-sm mr-2 mb-1">
                    <option value="">Todas las etapas</option>
                    @foreach ($etapasErrorLiq as $cod => $label)
                        <option value="{{ $cod }}" @selected(($filtroEtapaError ?? '') === $cod)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="input-group input-group-sm mr-2 mb-1" style="max-width: 22rem;">
                    <input type="search" name="err_q" class="form-control"
                           value="{{ $filtroQError ?? '' }}"
                           placeholder="Legajo, nombre, concepto o mensaje…"
                           autocomplete="off">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-outline-secondary">Filtrar</button>
                        @if (($filtroEtapaError ?? '') !== '' || ($filtroQError ?? '') !== '')
                            <a href="{{ route('resultado_liquidacion_sueldos', ['id' => $liq->id]) }}#panel-errores-liquidacion"
                               class="btn btn-outline-secondary" title="Limpiar filtros de errores">
                                <i class="fa fa-times"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0">
                    <thead>
                        <tr style="background-color:#F5B7B1;color:#17202A;">
                            <th style="width:70px">Legajo</th>
                            <th>Empleado</th>
                            <th style="width:70px">C&oacute;d.</th>
                            <th>Concepto</th>
                            <th style="width:90px">Etapa</th>
                            <th>Mensaje</th>
                            <th style="width:210px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($erroresLiquidacion as $err)
                            @php
                                $posErr = $err->formulaPosicion();
                                $campoErr = $err->formulaCampoId();
                                $editParamsRow = array_filter([
                                    'resaltar_pos' => $posErr >= 0 ? $posErr : null,
                                    'campo' => $campoErr,
                                    'origen' => 'liquidacion',
                                    'liquidacion_id' => $liq->id,
                                ], fn ($v) => $v !== null && $v !== '');
                                $conceptoIdRow = $err->concepto_id
                                    ?: (($conceptoIdPorCodigo ?? collect())[(int) $err->concepto_codigo] ?? null);
                            @endphp
                            <tr>
                                <td>{{ $err->legajo ?? '—' }}</td>
                                <td>{{ $err->apellido_nombre ?: '—' }}</td>
                                <td>{{ $err->concepto_codigo ?? '—' }}</td>
                                <td>{{ $err->concepto_descripcion ?: '—' }}</td>
                                <td>
                                    <span class="badge badge-{{ $etapaBadge[$err->etapa] ?? 'secondary' }}">
                                        {{ $err->etapaLabel() }}
                                    </span>
                                </td>
                                <td class="small" style="white-space:pre-wrap;word-break:break-word;">{{ $err->mensaje }}</td>
                                <td class="text-nowrap">
                                    @if ($err->empleado_id)
                                        <a href="{{ route('trazar_liquidacion_sueldos', ['id' => $liq->id, 'empleadoId' => $err->empleado_id]) }}"
                                           class="btn btn-xs btn-info mb-1"
                                           target="_blank" rel="noopener"
                                           title="Ver cálculo paso a paso de este empleado">
                                            <i class="fa fa-bug"></i> Depurar
                                        </a>
                                    @endif
                                    @if ($err->etapa === 'formula' && $puedeEditarConc && $conceptoIdRow)
                                        <a href="{{ route('editar_concepto_sueldos', ['id' => $conceptoIdRow] + $editParamsRow) }}"
                                           class="btn btn-xs btn-danger mb-1"
                                           target="_blank" rel="noopener"
                                           title="Editar fórmula y marcar el error">
                                            <i class="fa fa-edit"></i> Editar
                                        </a>
                                    @elseif ($err->etapa === 'formula' && $conceptoIdRow)
                                        <a href="{{ route('editar_concepto_sueldos', ['id' => $conceptoIdRow, 'origen' => 'modal_consulta', 'vista' => 'consulta'] + $editParamsRow) }}"
                                           class="btn btn-xs btn-outline-danger mb-1"
                                           target="_blank" rel="noopener">
                                            <i class="fa fa-eye"></i> Ver
                                        </a>
                                    @endif
                                    @if (! $err->empleado_id && ! ($err->etapa === 'formula' && $conceptoIdRow))
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    Ning&uacute;n error coincide con el filtro.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">
                {{ $erroresLiquidacion->fragment('panel-errores-liquidacion')->links() }}
            </div>
        </div>
    </div>
@endif
