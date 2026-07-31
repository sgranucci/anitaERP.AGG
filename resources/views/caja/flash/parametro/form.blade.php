@php
    $soloLecturaClave = !empty($data->id);
    $periodoInput = old('periodo', $data->periodo_input ?: '');
    if ($periodoInput === '' && preg_match('/^\d{6}$/', (string) ($data->periodo ?? ''))) {
        $periodoInput = substr($data->periodo, 0, 4).'-'.substr($data->periodo, 4, 2);
    }
    $indices = old('indices', $indices ?? []);
@endphp

@include('includes.tabs-activas-estilos')
<div class="tabs-activas">
    <ul class="nav nav-tabs" id="tabs-flash-parametro" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tab-encabezado" role="tab">
                <i class="fa fa-header"></i> Encabezado (budgets)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-indices" role="tab">
                <i class="fa fa-calendar"></i> Seasonal indexes (d&iacute;a a d&iacute;a)
            </a>
        </li>
    </ul>
</div>

<div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="tab-encabezado" role="tabpanel">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
            'solo_lectura' => $soloLecturaClave,
        ])

        <div class="form-group row">
            <label for="periodo" class="col-lg-3 col-form-label requerido">Per&iacute;odo (mes)</label>
            <div class="col-lg-3">
                <input type="month"
                       name="periodo"
                       id="periodo"
                       class="form-control"
                       required
                       value="{{ $periodoInput }}"
                       {{ $soloLecturaClave ? 'readonly' : '' }}>
                <small class="form-text text-muted">Un registro por empresa y mes.</small>
            </div>
            @if(!$soloLecturaClave)
            <div class="col-lg-3">
                <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btn-generar-dias">
                    <i class="fa fa-refresh"></i> Generar d&iacute;as del mes
                </button>
            </div>
            @endif
        </div>

        <hr>
        <h5 class="text-muted mb-3">Budgets del mes</h5>

        <div class="row">
            <div class="form-group col-md-4">
                <label for="budget_total">Budget total</label>
                <input type="text" inputmode="decimal" name="budget_total" id="budget_total"
                       class="form-control text-right js-monto-ar flash-budget-decimal" autocomplete="off"
                       value="{{ number_format((float) old('budget_total', $data->budget_total ?? 0), 2, ',', '.') }}">
            </div>
            <div class="form-group col-md-4">
                <label for="budget_slot">Budget slots</label>
                <input type="text" inputmode="decimal" name="budget_slot" id="budget_slot"
                       class="form-control text-right js-monto-ar flash-budget-decimal" autocomplete="off"
                       value="{{ number_format((float) old('budget_slot', $data->budget_slot ?? 0), 2, ',', '.') }}">
            </div>
            <div class="form-group col-md-4">
                <label for="budget_rul">Budget ruleta</label>
                <input type="text" inputmode="decimal" name="budget_rul" id="budget_rul"
                       class="form-control text-right js-monto-ar flash-budget-decimal" autocomplete="off"
                       value="{{ number_format((float) old('budget_rul', $data->budget_rul ?? 0), 2, ',', '.') }}">
            </div>
            <div class="form-group col-md-4">
                <label for="budget_poker">Budget poker</label>
                <input type="text" inputmode="decimal" name="budget_poker" id="budget_poker"
                       class="form-control text-right js-monto-ar flash-budget-decimal" autocomplete="off"
                       value="{{ number_format((float) old('budget_poker', $data->budget_poker ?? 0), 2, ',', '.') }}">
            </div>
            <div class="form-group col-md-4">
                <label for="budget_bingo">Budget bingo</label>
                <input type="text" inputmode="decimal" name="budget_bingo" id="budget_bingo"
                       class="form-control text-right js-monto-ar flash-budget-decimal" autocomplete="off"
                       value="{{ number_format((float) old('budget_bingo', $data->budget_bingo ?? 0), 2, ',', '.') }}">
            </div>
            <div class="form-group col-md-4">
                <label for="budget_f_b">Budget F&amp;B (AyB)</label>
                <input type="text" inputmode="decimal" name="budget_f_b" id="budget_f_b"
                       class="form-control text-right js-monto-ar flash-budget-decimal" autocomplete="off"
                       value="{{ number_format((float) old('budget_f_b', $data->budget_f_b ?? 0), 2, ',', '.') }}">
            </div>
            <div class="form-group col-md-4">
                <label for="budget_estac">Budget estacionamiento</label>
                <input type="text" inputmode="decimal" name="budget_estac" id="budget_estac"
                       class="form-control text-right js-monto-ar flash-budget-decimal" autocomplete="off"
                       value="{{ number_format((float) old('budget_estac', $data->budget_estac ?? 0), 2, ',', '.') }}">
            </div>
            <div class="form-group col-md-4">
                <label for="budget_pos">Budget positions (POS)</label>
                <input type="text" inputmode="numeric" name="budget_pos" id="budget_pos"
                       class="form-control text-right flash-budget-entero" autocomplete="off"
                       value="{{ number_format((int) old('budget_pos', $data->budget_pos ?? 0), 0, ',', '.') }}">
            </div>
        </div>

        <div class="alert alert-light border mt-2 mb-0">
            <strong>Totales seasonality (calculados al guardar):</strong>
            Gastro <span id="tot-season">{{ number_format((float) ($data->total_season ?? 0), 4, ',', '.') }}</span>
            &mdash; Bingo <span id="tot-sbingo">{{ number_format((float) ($data->total_sbingo ?? 0), 4, ',', '.') }}</span>
            &mdash; Slot <span id="tot-sslot">{{ number_format((float) ($data->total_sslot ?? 0), 4, ',', '.') }}</span>
            &mdash; Ruleta <span id="tot-srul">{{ number_format((float) ($data->total_srul ?? 0), 4, ',', '.') }}</span>
            &mdash; Poker <span id="tot-spoker">{{ number_format((float) ($data->total_spoker ?? 0), 4, ',', '.') }}</span>
            &mdash; Estac. <span id="tot-sestac">{{ number_format((float) ($data->total_s_estac ?? 0), 4, ',', '.') }}</span>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-indices" role="tabpanel">
        <p class="text-muted small">
            Cargue customers, veh&iacute;culos y seasonality index por d&iacute;a.
            Los totales del encabezado se recalculan al guardar.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover" id="tabla-indices-flash">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th class="width90">Fecha</th>
                        <th class="width80 text-right">Veh&iacute;culos</th>
                        <th class="width90 text-right">Customers</th>
                        <th class="text-right">S. Gastro</th>
                        <th class="text-right">S. Bingo</th>
                        <th class="text-right">S. Slot</th>
                        <th class="text-right">S. Ruleta</th>
                        <th class="text-right">S. Poker</th>
                        <th class="text-right">S. Estac.</th>
                    </tr>
                </thead>
                <tbody id="tbody-indices-flash">
                    @foreach($indices as $i => $fila)
                    <tr data-fila-indice>
                        <td>
                            <input type="hidden" name="indices[{{ $i }}][fecha]" value="{{ $fila['fecha'] ?? '' }}">
                            <span class="fecha-label">{{ isset($fila['fecha']) ? \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') : '' }}</span>
                        </td>
                        <td>
                            <input type="number" min="0" step="1" class="form-control form-control-sm text-right idx-num"
                                   name="indices[{{ $i }}][vehiculos]" value="{{ $fila['vehiculos'] ?? 0 }}">
                        </td>
                        <td>
                            <input type="number" min="0" step="1" class="form-control form-control-sm text-right idx-num"
                                   name="indices[{{ $i }}][customer]" value="{{ $fila['customer'] ?? 0 }}">
                        </td>
                        <td>
                            <input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season"
                                   name="indices[{{ $i }}][season_index]" value="{{ $fila['season_index'] ?? 0 }}">
                        </td>
                        <td>
                            <input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season"
                                   name="indices[{{ $i }}][sindex_bingo]" value="{{ $fila['sindex_bingo'] ?? 0 }}">
                        </td>
                        <td>
                            <input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season"
                                   name="indices[{{ $i }}][sindex_slot]" value="{{ $fila['sindex_slot'] ?? 0 }}">
                        </td>
                        <td>
                            <input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season"
                                   name="indices[{{ $i }}][sindex_rul]" value="{{ $fila['sindex_rul'] ?? 0 }}">
                        </td>
                        <td>
                            <input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season"
                                   name="indices[{{ $i }}][sindex_poker]" value="{{ $fila['sindex_poker'] ?? 0 }}">
                        </td>
                        <td>
                            <input type="number" min="0" step="0.0001" class="form-control form-control-sm text-right idx-season"
                                   name="indices[{{ $i }}][sindex_estac]" value="{{ $fila['sindex_estac'] ?? 0 }}">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight:bold;background:#f8f9fa;">
                        <td colspan="3" class="text-right">Totales &iacute;ndices</td>
                        <td class="text-right" id="foot-season">0</td>
                        <td class="text-right" id="foot-bingo">0</td>
                        <td class="text-right" id="foot-slot">0</td>
                        <td class="text-right" id="foot-rul">0</td>
                        <td class="text-right" id="foot-poker">0</td>
                        <td class="text-right" id="foot-estac">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
