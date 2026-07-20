<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo</label>
    <div class="col-lg-3">
        @if (isset($data))
            <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
        @else
            <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                   value="{{ old('codigo') }}"
                   placeholder="Autom&aacute;tico si se deja vac&iacute;o"/>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label requerido">Descripci&oacute;n</label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="30" required
               value="{{ old('descripcion', $data->descripcion ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="clase" class="col-lg-3 col-form-label">Clase (liquidaci&oacute;n final)</label>
    <div class="col-lg-6">
        <select name="clase" id="clase" class="form-control">
            @foreach (\App\Support\Sueldos\MotivoEgresoClase::CLASES as $val => $label)
                <option value="{{ $val }}" {{ old('clase', $data->clase ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Define qu&eacute; conceptos disparan en la liquidaci&oacute;n final (indemnizaciones, preaviso, etc.).</small>
    </div>
</div>
