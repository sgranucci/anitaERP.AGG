@php
    $cajaQueryForm = $caja_query ?? \App\Models\Caja\Caja::query()->orderBy('id')->get(['id', 'nombre']);
    $cajaIdForm = (int) old('caja_id', $data->caja_id ?? config('caja.caja_default_id', 1));
@endphp
<div class="form-group row">
    <label for="caja_id" class="col-lg-3 col-form-label requerido">Caja de recepción</label>
    <div class="col-lg-8">
        <select name="caja_id" id="caja_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($cajaQueryForm as $cajaItem)
                <option value="{{ $cajaItem->id }}" {{ $cajaIdForm === (int) $cajaItem->id ? 'selected' : '' }}>
                    {{ $cajaItem->id }} — {{ $cajaItem->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            Caja física a la que pertenece esta terminal (recepción de rendiciones / movimientos).
            Si la PC no está configurada, el sistema usa la caja default del entorno.
        </small>
    </div>
</div>
