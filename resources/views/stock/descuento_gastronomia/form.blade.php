<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">Código Anita</label>
    <div class="col-lg-8">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" maxlength="50"/>
        <small class="form-text text-muted">Corresponde a dto_codigo en Anita (clave de sincronización).</small>
    </div>
</div>
<div class="form-group row">
    <label for="tipovalor" class="col-lg-3 col-form-label requerido">Tipo de valor</label>
    <div class="col-lg-8">
        <select name="tipovalor" id="tipovalor" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($tiposValor as $clave => $etiqueta)
                <option value="{{ $clave }}" {{ old('tipovalor', $data->tipovalor ?? '') === $clave ? 'selected' : '' }}>
                    {{ $etiqueta }} ({{ $clave }})
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="valor" class="col-lg-3 col-form-label requerido">Valor</label>
    <div class="col-lg-8">
        <input type="number" name="valor" id="valor" class="form-control" value="{{ old('valor', $data->valor ?? '') }}" required step="any"/>
    </div>
</div>
