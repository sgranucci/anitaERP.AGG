@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
])

<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control"
            value="{{ old('codigo', $data->codigo ?? '') }}" required maxlength="20" autocomplete="off"
            @if (!empty($data->id)) readonly @endif/>
        <small class="form-text text-muted">Código en Anita (depm_deposito).</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Descripción</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control"
            value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="50"
            placeholder="Descripción"/>
        <small class="form-text text-muted">Descripción = depm_desc.</small>
    </div>
</div>
<div class="form-group row">
    <label for="tipodeposito" class="col-lg-3 col-form-label requerido">Tipo de depósito</label>
    <div class="col-lg-4">
        <select id="tipodeposito" name="tipodeposito" class="form-control" required>
            <option value="">-- Elija tipo de depósito --</option>
            @foreach($tipodeposito_enum as $tipodeposito)
                @if ($tipodeposito['nombre'] == old('tipodeposito', $data->tipodeposito ?? ''))
                    <option value="{{ $tipodeposito['nombre'] }}" selected>{{ $tipodeposito['nombre'] }}</option>
                @else
                    <option value="{{ $tipodeposito['nombre'] }}">{{ $tipodeposito['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
