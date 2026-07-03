<div class="form-group row">
    <label for="codigo_usuario" class="col-lg-3 col-form-label requerido">C&oacute;digo usuario</label>
    <div class="col-lg-8">
        @if (! empty($data->id))
            <input type="text" class="form-control" value="{{ $data->codigo_usuario }}" readonly>
            <input type="hidden" name="codigo_usuario" value="{{ old('codigo_usuario', $data->codigo_usuario) }}">
        @else
            <input type="number" name="codigo_usuario" id="codigo_usuario" class="form-control" min="1" step="1" required
                   value="{{ old('codigo_usuario', $data->codigo_usuario ?? '') }}">
        @endif
        <small class="form-text text-muted">Identificador legacy Anita (<code>usuv_usuario</code>).</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="255" required
               value="{{ old('nombre', $data->nombre ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="password" class="col-lg-3 col-form-label requerido">Contrase&ntilde;a</label>
    <div class="col-lg-8">
        <input type="text" name="password" id="password" class="form-control" maxlength="15" required
               value="{{ old('password', $data->password ?? '') }}" autocomplete="off">
        <small class="form-text text-muted">Clave de acceso en terminal de viandas (Anita <code>usuv_password</code>).</small>
    </div>
</div>
<div class="form-group row">
    <label for="centrocosto_id" class="col-lg-3 col-form-label">Centro de costo</label>
    <div class="col-lg-8">
        <select name="centrocosto_id" id="centrocosto_id" class="form-control">
            <option value="">Sin asignar&hellip;</option>
            @foreach ($centrocosto_query as $cc)
                <option value="{{ $cc->id }}" {{ (int) old('centrocosto_id', $data->centrocosto_id ?? 0) === (int) $cc->id ? 'selected' : '' }}>
                    {{ $cc->codigo }} — {{ $cc->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="tipo_usuario" class="col-lg-3 col-form-label requerido">Tipo usuario</label>
    <div class="col-lg-8">
        <select name="tipo_usuario" id="tipo_usuario" class="form-control" required>
            @foreach (\App\Support\Ventas\ViandaUsuarioTipoSupport::ETIQUETAS as $codigo => $etiqueta)
                <option value="{{ $codigo }}" {{ old('tipo_usuario', $data->tipo_usuario ?? 'L') === $codigo ? 'selected' : '' }}>
                    {{ $codigo }} — {{ $etiqueta }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="vianda_tipo_menu_id" class="col-lg-3 col-form-label">Tipo de men&uacute;</label>
    <div class="col-lg-8">
        <select name="vianda_tipo_menu_id" id="vianda_tipo_menu_id" class="form-control">
            <option value="">Sin asignar&hellip;</option>
            @foreach ($tipo_menu_query as $tm)
                <option value="{{ $tm->id }}" {{ (int) old('vianda_tipo_menu_id', $data->vianda_tipo_menu_id ?? 0) === (int) $tm->id ? 'selected' : '' }}>
                    {{ $tm->nombre }}
                    @if (isset($tm->estado) && $tm->estado === 'I')
                        (inactivo)
                    @endif
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <div class="col-lg-8">
        <select name="estado" id="estado" class="form-control" required>
            <option value="A" {{ old('estado', $data->estado ?? 'A') === 'A' ? 'selected' : '' }}>Activo</option>
            <option value="I" {{ old('estado', $data->estado ?? 'A') === 'I' ? 'selected' : '' }}>Inactivo</option>
        </select>
        <small class="form-text text-muted">Los inactivos no se actualizan al sincronizar desde Anita.</small>
    </div>
</div>
