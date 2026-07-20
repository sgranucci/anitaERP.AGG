<div class="form-group row">
    <label for="tipo" class="col-lg-3 col-form-label requerido">Tipo</label>
    <div class="col-lg-3">
        <select name="tipo" id="tipo" class="form-control" required>
            @foreach ($tipos as $t)
                <option value="{{ $t }}" {{ old('tipo', $data->tipo ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="orden" class="col-lg-3 col-form-label requerido">Orden</label>
    <div class="col-lg-2">
        <input type="number" name="orden" id="orden" class="form-control" min="0" required
               value="{{ old('orden', $data->orden ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="desde" class="col-lg-3 col-form-label requerido">Desde</label>
    <div class="col-lg-3">
        <input type="number" step="0.01" name="desde" id="desde" class="form-control text-right" min="0" required
               value="{{ old('desde', isset($data) ? number_format((float) $data->desde, 2, '.', '') : '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="hasta" class="col-lg-3 col-form-label requerido">Hasta</label>
    <div class="col-lg-3">
        <input type="number" step="0.01" name="hasta" id="hasta" class="form-control text-right" min="0" required
               value="{{ old('hasta', isset($data) ? number_format((float) $data->hasta, 2, '.', '') : '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="sancion" class="col-lg-3 col-form-label requerido">Sanci&oacute;n</label>
    <div class="col-lg-6">
        <input type="text" name="sancion" id="sancion" class="form-control" maxlength="40" required
               value="{{ old('sancion', $data->sancion ?? '') }}"/>
    </div>
</div>
