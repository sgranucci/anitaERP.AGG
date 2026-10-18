<div class="form-group row">
    <label for="codigo" class="col-lg-4 control-label text-right pr-2 requerido">C&oacute;digo</label>
    <div class="col-lg-3">
        <input type="number" name="codigo" id="codigo" class="form-control" min="1" required
               value="{{ old('codigo', $data->codigo ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-7">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="80" required
               value="{{ old('nombre', $data->nombre ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="tipo_valor" class="col-lg-4 control-label text-right pr-2 requerido">Tipo valor</label>
    <div class="col-lg-4">
        <select name="tipo_valor" id="tipo_valor" class="form-control" required>
            @foreach ($tipos as $tipo)
                <option value="{{ $tipo['valor'] }}"
                    {{ (string) old('tipo_valor', $data->tipo_valor ?? 'C') === $tipo['valor'] ? 'selected' : '' }}>
                    {{ $tipo['nombre'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
