<div class="form-group row">
    <label for="codigo" class="col-lg-4 control-label text-right pr-2">Código</label>
    <div class="col-lg-3">
        @if (isset($data))
            <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
        @else
            <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                   value="{{ old('codigo') }}"
                   placeholder="Automático si se deja vacío"/>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="60" required
               value="{{ old('nombre', $data->nombre ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-4 control-label text-right pr-2">Activo</label>
    <div class="col-lg-6">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" name="activo" id="activo" value="1"
               {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
    </div>
</div>
