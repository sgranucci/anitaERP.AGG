@php
    $cajaQueryForm = $caja_query ?? \App\Models\Caja\Caja::query()->orderBy('id')->get(['id', 'nombre']);
    $cajaIdForm = (int) old('caja_id', $data->caja_id ?? config('caja.caja_default_id', 1));
    $cajaColLabel = $caja_col_label ?? 'col-lg-3 col-form-label text-right pr-2';
    $cajaColInput = $caja_col_input ?? 'col-lg-8';
    $cajaAyuda = $caja_ayuda ?? 'Caja física a la que pertenece esta terminal: ahí caen las rendiciones y movimientos de caja cuando no hay asignación diaria de cajero. Si la PC no está configurada, se usa la caja default del entorno.';
@endphp
<div class="form-group row">
    <label for="caja_id" class="{{ $cajaColLabel }} requerido">Caja de recepción</label>
    <div class="{{ $cajaColInput }}">
        <select name="caja_id" id="caja_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($cajaQueryForm as $cajaItem)
                <option value="{{ $cajaItem->id }}" {{ $cajaIdForm === (int) $cajaItem->id ? 'selected' : '' }}>
                    {{ $cajaItem->id }} — {{ $cajaItem->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">{{ $cajaAyuda }}</small>
    </div>
</div>
