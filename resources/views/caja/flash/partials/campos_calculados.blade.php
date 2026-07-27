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
@endphp

<input type="hidden" name="flash_valores_desde_formulario" id="flash_valores_desde_formulario" value="{{ old('flash_valores_desde_formulario', isset($data->id) ? '1' : '0') }}">

<div class="table-responsive mb-3">
    <table class="table table-sm table-bordered mb-0 flash-tabla-gaming">
        <thead class="thead-light">
            <tr>
                <th style="min-width: 9rem;"></th>
                <th class="text-right" style="min-width: 7rem;">Coin in</th>
                <th class="text-right" style="min-width: 7rem;">Drop</th>
                <th class="text-right" style="min-width: 7rem;">Win</th>
                <th class="text-right" style="min-width: 5rem;">Cantidad</th>
                <th class="text-right" style="min-width: 7rem;">Win on-line</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th class="align-middle bg-light">Slots</th>
                <td><input type="text" inputmode="decimal" name="slot_coin_in" id="slot_coin_in" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('slot_coin_in')) }}" autocomplete="off"></td>
                <td><input type="text" inputmode="decimal" name="slot_d" id="slot_d" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('slot_d')) }}" autocomplete="off"></td>
                <td><input type="text" inputmode="decimal" name="slot_r" id="slot_r" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('slot_r')) }}" autocomplete="off"></td>
                <td><input type="text" inputmode="numeric" name="cant_slots" id="cant_slots" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-entero" value="{{ $fmtEntero($valorCampo('cant_slots')) }}" autocomplete="off"></td>
                <td><input type="text" inputmode="decimal" name="win_ol_slot" id="win_ol_slot" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('win_ol_slot')) }}" autocomplete="off"></td>
            </tr>
            <tr>
                <th class="align-middle bg-light">Ruleta electr.</th>
                <td><input type="text" inputmode="decimal" name="rul_coin_in" id="rul_coin_in" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('rul_coin_in')) }}" autocomplete="off"></td>
                <td><input type="text" inputmode="decimal" name="rul_d" id="rul_d" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('rul_d')) }}" autocomplete="off"></td>
                <td><input type="text" inputmode="decimal" name="rul_r" id="rul_r" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('rul_r')) }}" autocomplete="off"></td>
                <td><input type="text" inputmode="numeric" name="cant_rul" id="cant_rul" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-entero" value="{{ $fmtEntero($valorCampo('cant_rul')) }}" autocomplete="off"></td>
                <td><input type="text" inputmode="decimal" name="win_ol_rul" id="win_ol_rul" class="form-control form-control-sm text-right flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('win_ol_rul')) }}" autocomplete="off"></td>
            </tr>
        </tbody>
    </table>
</div>

<input type="hidden" name="soft_count" id="soft_count" value="{{ $fmtDecimal($valorCampo('soft_count')) }}">
<input type="hidden" name="hard_count" id="hard_count" value="{{ $fmtDecimal($valorCampo('hard_count')) }}">
<input type="hidden" name="soft_rul" id="soft_rul" value="{{ $fmtDecimal($valorCampo('soft_rul')) }}">
<input type="hidden" name="hard_rul" id="hard_rul" value="{{ $fmtDecimal($valorCampo('hard_rul')) }}">

<h6 class="text-muted mb-2">Bingo, gastronom&iacute;a y estacionamiento</h6>
<div class="row">
    <div class="form-group col-md-4 col-sm-6">
        <label for="bingo_cant_carton" class="small mb-1">Cartones bingo</label>
        <input type="text" inputmode="numeric" name="bingo_cant_carton" id="bingo_cant_carton" class="form-control form-control-sm flash-campo-calculado flash-campo-entero" value="{{ $fmtEntero($valorCampo('bingo_cant_carton')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="bingo_total_venta" class="small mb-1">Ventas bingo</label>
        <input type="text" inputmode="decimal" name="bingo_total_venta" id="bingo_total_venta" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('bingo_total_venta')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="bingo_resultado" class="small mb-1">Resultado bingo</label>
        <input type="text" inputmode="decimal" name="bingo_resultado" id="bingo_resultado" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('bingo_resultado')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="ayb" class="small mb-1">AyB (food &amp; beverage)</label>
        <input type="text" inputmode="decimal" name="ayb" id="ayb" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('ayb')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="estac" class="small mb-1">Estacionamiento (parking)</label>
        <input type="text" inputmode="decimal" name="estac" id="estac" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('estac')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="vending" class="small mb-1">Vending</label>
        <input type="text" inputmode="decimal" name="vending" id="vending" class="form-control form-control-sm flash-campo-calculado flash-campo-decimal" value="{{ $fmtDecimal($valorCampo('vending')) }}" autocomplete="off">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="cant_vehic" class="small mb-1">Cant. veh&iacute;culos</label>
        <input type="text" inputmode="numeric" name="cant_vehic" id="cant_vehic" class="form-control form-control-sm flash-campo-calculado flash-campo-entero" value="{{ $fmtEntero($valorCampo('cant_vehic')) }}" autocomplete="off">
    </div>
</div>

@if(!empty($data->calculado_en))
    <p class="text-muted small mb-0">
        &Uacute;ltimo c&aacute;lculo: {{ $data->calculado_en->format('d/m/Y H:i') }}
    </p>
@endif
