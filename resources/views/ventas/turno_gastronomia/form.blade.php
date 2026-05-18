@php
    $horaDesde = old('hora_desde', isset($data->hora_desde) && $data->hora_desde ? substr((string) $data->hora_desde, 0, 5) : '');
    $horaHasta = old('hora_hasta', isset($data->hora_hasta) && $data->hora_hasta ? substr((string) $data->hora_hasta, 0, 5) : '');
    $activo = (bool) old('activo', $data->activo ?? true);
@endphp
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">Código</label>
    <div class="col-lg-8">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" maxlength="50"/>
        <small class="form-text text-muted">Opcional. Único por empresa.</small>
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
<div class="form-group row">
    <label for="hora_desde" class="col-lg-3 col-form-label">Hora desde</label>
    <div class="col-lg-3">
        <input type="time" name="hora_desde" id="hora_desde" class="form-control" value="{{ $horaDesde }}"/>
    </div>
    <label for="hora_hasta" class="col-lg-2 col-form-label">Hora hasta</label>
    <div class="col-lg-3">
        <input type="time" name="hora_hasta" id="hora_hasta" class="form-control" value="{{ $horaHasta }}"/>
    </div>
</div>
<div class="form-group row">
    <div class="col-lg-3"></div>
    <div class="col-lg-8">
        <small class="form-text text-muted">
            Si el turno termina al día siguiente (ej. noche 22:00 a 07:00), ingrese la hora de fin menor que la de inicio.
            El sistema lo interpretará como cierre al día calendario siguiente.
        </small>
    </div>
</div>
<div class="form-group row">
    <label for="orden" class="col-lg-3 col-form-label">Orden</label>
    <div class="col-lg-3">
        <input type="number" name="orden" id="orden" class="form-control" value="{{ old('orden', $data->orden ?? 0) }}" min="0" max="9999"/>
    </div>
</div>
<div class="form-group row">
    <label for="activo" class="col-lg-3 col-form-label">Activo</label>
    <div class="col-lg-8">
        <input type="hidden" name="activo" value="0">
        <div class="form-check">
            <input type="checkbox" name="activo" id="activo" class="form-check-input" value="1" {{ $activo ? 'checked' : '' }}>
            <label class="form-check-label" for="activo">Turno habilitado</label>
        </div>
    </div>
</div>
