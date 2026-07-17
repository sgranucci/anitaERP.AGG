@php
    $valorCampo = static function (string $campo, $default = 0) use ($data) {
        return old($campo, $data->{$campo} ?? $default);
    };
@endphp

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
                <td><input type="number" step="0.01" name="slot_coin_in" id="slot_coin_in" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('slot_coin_in') }}"></td>
                <td><input type="number" step="0.01" name="slot_d" id="slot_d" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('slot_d') }}"></td>
                <td><input type="number" step="0.01" name="slot_r" id="slot_r" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('slot_r') }}"></td>
                <td><input type="number" step="1" name="cant_slots" id="cant_slots" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('cant_slots') }}"></td>
                <td><input type="number" step="0.01" name="win_ol_slot" id="win_ol_slot" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('win_ol_slot') }}"></td>
            </tr>
            <tr>
                <th class="align-middle bg-light">Ruleta electr.</th>
                <td><input type="number" step="0.01" name="rul_coin_in" id="rul_coin_in" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('rul_coin_in') }}"></td>
                <td><input type="number" step="0.01" name="rul_d" id="rul_d" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('rul_d') }}"></td>
                <td><input type="number" step="0.01" name="rul_r" id="rul_r" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('rul_r') }}"></td>
                <td><input type="number" step="1" name="cant_rul" id="cant_rul" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('cant_rul') }}"></td>
                <td><input type="number" step="0.01" name="win_ol_rul" id="win_ol_rul" class="form-control form-control-sm text-right flash-campo-calculado" value="{{ $valorCampo('win_ol_rul') }}"></td>
            </tr>
        </tbody>
    </table>
</div>

<input type="hidden" name="soft_count" id="soft_count" value="{{ $valorCampo('soft_count') }}">
<input type="hidden" name="hard_count" id="hard_count" value="{{ $valorCampo('hard_count') }}">
<input type="hidden" name="soft_rul" id="soft_rul" value="{{ $valorCampo('soft_rul') }}">
<input type="hidden" name="hard_rul" id="hard_rul" value="{{ $valorCampo('hard_rul') }}">

<h6 class="text-muted mb-2">Bingo, gastronom&iacute;a y estacionamiento</h6>
<div class="row">
    <div class="form-group col-md-4 col-sm-6">
        <label for="bingo_cant_carton" class="small mb-1">Cartones bingo</label>
        <input type="number" step="1" name="bingo_cant_carton" id="bingo_cant_carton" class="form-control form-control-sm flash-campo-calculado" value="{{ $valorCampo('bingo_cant_carton') }}">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="bingo_total_venta" class="small mb-1">Ventas bingo</label>
        <input type="number" step="0.01" name="bingo_total_venta" id="bingo_total_venta" class="form-control form-control-sm flash-campo-calculado" value="{{ $valorCampo('bingo_total_venta') }}">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="bingo_resultado" class="small mb-1">Resultado bingo</label>
        <input type="number" step="0.01" name="bingo_resultado" id="bingo_resultado" class="form-control form-control-sm flash-campo-calculado" value="{{ $valorCampo('bingo_resultado') }}">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="ayb" class="small mb-1">AyB (food &amp; beverage)</label>
        <input type="number" step="0.01" name="ayb" id="ayb" class="form-control form-control-sm flash-campo-calculado" value="{{ $valorCampo('ayb') }}">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="estac" class="small mb-1">Estacionamiento (parking)</label>
        <input type="number" step="0.01" name="estac" id="estac" class="form-control form-control-sm flash-campo-calculado" value="{{ $valorCampo('estac') }}">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="vending" class="small mb-1">Vending</label>
        <input type="number" step="0.01" name="vending" id="vending" class="form-control form-control-sm flash-campo-calculado" value="{{ $valorCampo('vending') }}">
    </div>
    <div class="form-group col-md-4 col-sm-6">
        <label for="cant_vehic" class="small mb-1">Cant. veh&iacute;culos</label>
        <input type="number" step="1" name="cant_vehic" id="cant_vehic" class="form-control form-control-sm flash-campo-calculado" value="{{ $valorCampo('cant_vehic') }}">
    </div>
</div>

@if(!empty($data->calculado_en))
    <p class="text-muted small mb-0">
        &Uacute;ltimo c&aacute;lculo: {{ $data->calculado_en->format('d/m/Y H:i') }}
    </p>
@endif
