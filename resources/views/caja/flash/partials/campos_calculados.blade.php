@php
    $fmtDecimal = static function ($valor) {
        return number_format((float) ($valor ?? 0), 2, ',', '.');
    };
    $fmtEntero = static function ($valor) {
        return number_format((int) ($valor ?? 0), 0, ',', '.');
    };
    $valorCampo = static function (string $campo, $default = 0) use ($data) {
        return old($campo, $data->{$campo} ?? $default);
    };
    $btnOrigen = static function (string $campo) {
        return '<button type="button" class="btn btn-outline-info btn-sm flash-btn-origen" data-campo="'
            .e($campo).'" title="Ver origen y movimientos">'
            .'<i class="fa fa-search"></i></button>';
    };
@endphp

<input type="hidden" name="flash_valores_desde_formulario" id="flash_valores_desde_formulario" value="{{ old('flash_valores_desde_formulario', isset($data->id) ? '1' : '0') }}">

<div class="table-responsive mb-3">
    <table class="table table-sm table-bordered mb-0 flash-tabla-gaming">
        <thead class="thead-light">
            <tr>
                <th style="min-width: 9rem;"></th>
                <th class="text-right" style="min-width: 8rem;">Coin in</th>
                <th class="text-right" style="min-width: 8rem;">Drop</th>
                <th class="text-right" style="min-width: 8rem;">Win</th>
                <th class="text-right" style="min-width: 6rem;">Cantidad</th>
                <th class="text-right" style="min-width: 8rem;">Win on-line</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th class="align-middle bg-light">Slots</th>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="decimal" name="slot_coin_in" id="slot_coin_in" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('slot_coin_in')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('slot_coin_in') !!}</div>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="decimal" name="slot_d" id="slot_d" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('slot_d')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('slot_d') !!}</div>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="decimal" name="slot_r" id="slot_r" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('slot_r')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('slot_r') !!}</div>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="numeric" name="cant_slots" id="cant_slots" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-entero" value="{{ $fmtEntero($valorCampo('cant_slots')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('cant_slots') !!}</div>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="decimal" name="win_ol_slot" id="win_ol_slot" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('win_ol_slot')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('win_ol_slot') !!}</div>
                    </div>
                </td>
            </tr>
            <tr>
                <th class="align-middle bg-light">Ruleta electr.</th>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="decimal" name="rul_coin_in" id="rul_coin_in" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('rul_coin_in')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('rul_coin_in') !!}</div>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="decimal" name="rul_d" id="rul_d" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('rul_d')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('rul_d') !!}</div>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="decimal" name="rul_r" id="rul_r" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('rul_r')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('rul_r') !!}</div>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="numeric" name="cant_rul" id="cant_rul" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-entero" value="{{ $fmtEntero($valorCampo('cant_rul')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('cant_rul') !!}</div>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="decimal" name="win_ol_rul" id="win_ol_rul" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('win_ol_rul')) }}" autocomplete="off">
                        <div class="input-group-append">{!! $btnOrigen('win_ol_rul') !!}</div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<input type="hidden" name="soft_count" id="soft_count" value="{{ $fmtDecimal($valorCampo('soft_count')) }}">
<input type="hidden" name="hard_count" id="hard_count" value="{{ $fmtDecimal($valorCampo('hard_count')) }}">
<input type="hidden" name="soft_rul" id="soft_rul" value="{{ $fmtDecimal($valorCampo('soft_rul')) }}">
<input type="hidden" name="hard_rul" id="hard_rul" value="{{ $fmtDecimal($valorCampo('hard_rul')) }}">

<p class="small text-muted mb-3">
    Soft/hard count (ocultos, entran al c&aacute;lculo):
    <button type="button" class="btn btn-link btn-sm p-0 flash-btn-origen" data-campo="soft_count">soft slots</button> ·
    <button type="button" class="btn btn-link btn-sm p-0 flash-btn-origen" data-campo="hard_count">hard slots</button> ·
    <button type="button" class="btn btn-link btn-sm p-0 flash-btn-origen" data-campo="soft_rul">soft rul</button> ·
    <button type="button" class="btn btn-link btn-sm p-0 flash-btn-origen" data-campo="hard_rul">hard rul</button>
</p>

<h6 class="text-muted mb-2">Bingo, gastronom&iacute;a y estacionamiento</h6>
<div class="row">
    <div class="form-group col-md-4 col-sm-6">
        <label for="bingo_cant_carton" class="small mb-1">Cartones bingo {!! $btnOrigen('bingo_cant_carton') !!}</label>
        <input type="text" inputmode="numeric" name="bingo_cant_carton" id="bingo_cant_carton" class="form-control form-control-sm flash-campo-calculado flash-campo-entero" value="{{ $fmtEntero($valorCampo('bingo_cant_carton')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="bingo_total_venta" class="small mb-1">Ventas bingo {!! $btnOrigen('bingo_total_venta') !!}</label>
        <input type="text" inputmode="decimal" name="bingo_total_venta" id="bingo_total_venta" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('bingo_total_venta')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="bingo_resultado" class="small mb-1">Resultado bingo {!! $btnOrigen('bingo_resultado') !!}</label>
        <input type="text" inputmode="decimal" name="bingo_resultado" id="bingo_resultado" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('bingo_resultado')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="ayb" class="small mb-1">AyB (food &amp; beverage) {!! $btnOrigen('ayb') !!}</label>
        <input type="text" inputmode="decimal" name="ayb" id="ayb" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('ayb')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="estac" class="small mb-1">Estacionamiento (parking) {!! $btnOrigen('estac') !!}</label>
        <input type="text" inputmode="decimal" name="estac" id="estac" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('estac')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="vending" class="small mb-1">Vending {!! $btnOrigen('vending') !!}</label>
        <input type="text" inputmode="decimal" name="vending" id="vending" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('vending')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="cant_vehic" class="small mb-1">Cant. veh&iacute;culos {!! $btnOrigen('cant_vehic') !!}</label>
        <input type="text" inputmode="numeric" name="cant_vehic" id="cant_vehic" class="form-control form-control-sm flash-campo-calculado flash-campo-entero" value="{{ $fmtEntero($valorCampo('cant_vehic')) }}" autocomplete="off">
    </div>
</div>

@if(!empty($data->calculado_en))
    <p class="text-muted small mb-0">
        &Uacute;ltimo c&aacute;lculo: {{ $data->calculado_en->format('d/m/Y H:i') }}
    </p>
@endif
