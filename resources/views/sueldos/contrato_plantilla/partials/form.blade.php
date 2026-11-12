@php
    $data = $data ?? null;
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">Código</label>
    <div class="col-lg-4">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="40" required
               value="{{ old('codigo', $data->codigo ?? '') }}"
               placeholder="Ej. MOD12-ALE">
        <small class="text-muted">Referencia interna (puede coincidir con el nombre Anita).</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required
               value="{{ old('nombre', $data->nombre ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="tipo" class="col-lg-3 control-label text-right pr-2 requerido">Tipo</label>
    <div class="col-lg-4">
        <select name="tipo" id="tipo" class="form-control" required>
            @foreach ($tipos as $k => $lab)
                <option value="{{ $k }}" @selected(old('tipo', $data->tipo ?? '') === $k)>{{ $lab }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="archivo" class="col-lg-3 control-label text-right pr-2 {{ isset($data) ? '' : 'requerido' }}">
        Archivo Word
    </label>
    <div class="col-lg-6">
        <input type="file" name="archivo" id="archivo" class="form-control" accept=".doc,.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
               {{ isset($data) ? '' : 'required' }}>
        @if (isset($data))
            <small class="text-muted">Actual: {{ $data->archivo_nombre }} (v{{ $data->version }}). Subir uno nuevo reemplaza y sube la versión.</small>
        @else
            <small class="text-muted">.docx con placeholders <code>$tag$</code>.</small>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="observacion" class="col-lg-3 control-label text-right pr-2">Observación</label>
    <div class="col-lg-6">
        <textarea name="observacion" id="observacion" class="form-control" rows="2" maxlength="2000">{{ old('observacion', $data->observacion ?? '') }}</textarea>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Activa</label>
    <div class="col-lg-4">
        <input type="hidden" name="activo" value="0">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="activo" name="activo" value="1"
                   @checked((string) old('activo', ($data->activo ?? true) ? '1' : '0') === '1')>
            <label class="custom-control-label" for="activo">Disponible para emisión</label>
        </div>
    </div>
</div>
