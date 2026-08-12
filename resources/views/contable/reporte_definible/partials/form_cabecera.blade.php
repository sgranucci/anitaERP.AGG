<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Código</label>
    <div class="col-lg-3">
        <input type="number" name="codigo" class="form-control" min="1"
               value="{{ old('codigo', $data->codigo ?? '') }}"
               placeholder="Auto si vacío">
        <small class="form-text text-muted">Nro. de informe (Anita / ERP).</small>
    </div>
    <label class="col-lg-2 control-label text-right pr-2">Tipo</label>
    <div class="col-lg-4">
        <select name="tipo" class="form-control" required>
            @foreach ($tiposReporte as $k => $label)
                <option value="{{ $k }}" @if (old('tipo', $data->tipo ?? 'otro') === $k) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-9">
        <input type="text" name="nombre" class="form-control" maxlength="80" required
               value="{{ old('nombre', $data->nombre ?? '') }}"
               placeholder="Ej. Balance general, Estado de resultados…">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Título 1</label>
    <div class="col-lg-9">
        <input type="text" name="titulo1" class="form-control" maxlength="80"
               value="{{ old('titulo1', $data->titulo1 ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Título 2</label>
    <div class="col-lg-9">
        <input type="text" name="titulo2" class="form-control" maxlength="80"
               value="{{ old('titulo2', $data->titulo2 ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Observaciones</label>
    <div class="col-lg-9">
        <textarea name="observaciones" class="form-control" rows="2" maxlength="2000">{{ old('observaciones', $data->observaciones ?? '') }}</textarea>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Vigencia</label>
    <div class="col-lg-3">
        <input type="date" name="valido_desde" class="form-control"
               value="{{ old('valido_desde', optional($data->valido_desde ?? null)->format('Y-m-d')) }}">
        <small class="form-text text-muted">Válido desde</small>
    </div>
    <div class="col-lg-3">
        <input type="date" name="valido_hasta" class="form-control"
               value="{{ old('valido_hasta', optional($data->valido_hasta ?? null)->format('Y-m-d')) }}">
        <small class="form-text text-muted">Válido hasta</small>
    </div>
    <div class="col-lg-3">
        <select name="estado_publicacion" class="form-control">
            @php $est = old('estado_publicacion', $data->estado_publicacion ?? 'borrador'); @endphp
            <option value="borrador" @if ($est === 'borrador') selected @endif>Borrador</option>
            <option value="publicado" @if ($est === 'publicado') selected @endif>Publicado</option>
        </select>
        <small class="form-text text-muted">Estado de publicación</small>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Activo</label>
    <div class="col-lg-9">
        <div class="custom-control custom-switch mt-2">
            <input type="checkbox" class="custom-control-input" id="activo" name="activo" value="1"
                   @if (old('activo', $data->activo ?? true)) checked @endif>
            <label class="custom-control-label" for="activo">Disponible para ejecutar</label>
        </div>
    </div>
</div>
