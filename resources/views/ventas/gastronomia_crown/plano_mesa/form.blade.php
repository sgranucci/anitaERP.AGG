@php
    $lbl = 'col-lg-4 control-label text-right pr-2';
    $inp = 'col-lg-7';
    $esEdicion = (bool) ($data->id ?? null);
@endphp

<div class="row">
    <div class="col-lg-6">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
            'solo_lectura' => $esEdicion,
            'col_label' => $lbl,
            'col_input' => $inp,
        ])

        <div class="form-group row">
            <label for="sala_id" class="{{ $lbl }}">Sala</label>
            <div class="{{ $inp }}">
                <select name="sala_id" id="sala_id" class="form-control">
                    <option value="">Sin sala / todas</option>
                    @foreach (($sala_query ?? collect()) as $sala)
                        <option value="{{ $sala->id }}" {{ (int) old('sala_id', $data->sala_id ?? 0) === (int) $sala->id ? 'selected' : '' }}>
                            {{ $sala->codigo }} — {{ $sala->nombre }}
                        </option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Las mesas disponibles en el editor salen de la empresa y la sala elegidas.</small>
            </div>
        </div>

        <div class="form-group row">
            <label for="codigo" class="{{ $lbl }}">Código</label>
            <div class="{{ $inp }}">
                <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20"
                       value="{{ old('codigo', $data->codigo ?? '') }}" autocomplete="off">
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="form-group row">
            <label for="nombre" class="{{ $lbl }} requerido">Nombre</label>
            <div class="{{ $inp }}">
                <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required
                       value="{{ old('nombre', $data->nombre ?? '') }}" autocomplete="off">
            </div>
        </div>

        <div class="form-group row">
            <label for="orden" class="{{ $lbl }}">Orden</label>
            <div class="{{ $inp }}">
                <input type="number" name="orden" id="orden" class="form-control" min="0" max="9999"
                       value="{{ old('orden', $data->orden ?? 0) }}">
                <small class="form-text text-muted">Define el orden en que el POS muestra los planos de la PC.</small>
            </div>
        </div>

        <div class="form-group row">
            <label class="{{ $lbl }}">Estado</label>
            <div class="{{ $inp }}">
                <div class="custom-control custom-checkbox mt-2">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" class="custom-control-input" id="activo" name="activo" value="1"
                        {{ (int) old('activo', ($data->activo ?? true) ? 1 : 0) === 1 ? 'checked' : '' }}>
                    <label class="custom-control-label" for="activo">Activo (visible en el POS)</label>
                </div>
            </div>
        </div>
    </div>
</div>

@include('ventas.gastronomia_crown.plano_mesa.partials.editor', [
    'data' => $data,
    'esEdicion' => $esEdicion,
])
