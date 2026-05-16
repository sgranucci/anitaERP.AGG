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
        <small class="form-text text-muted">Corresponde a vend_codigo en Anita (clave de sincronización).</small>
    </div>
</div>
<div class="form-group row">
    <label for="empresa_id" class="col-lg-3 col-form-label requerido">Empresa</label>
    <div class="col-lg-8">
        <select name="empresa_id" id="empresa_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($empresa_query as $empresa)
                <option value="{{ $empresa->id }}" {{ (int) old('empresa_id', $data->empresa_id ?? config('cliente.EMPRESA_DEFAULT_ID')) === (int) $empresa->id ? 'selected' : '' }}>
                    {{ $empresa->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
