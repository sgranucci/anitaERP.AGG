@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'col_label' => 'col-lg-3 col-form-label',
    'col_input' => 'col-lg-8',
    'solo_lectura' => isset($data) && $data->exists,
])
@if (isset($data) && $data->exists && $data->numeroid)
<div class="form-group row">
    <label class="col-lg-3 col-form-label">Número Anita</label>
    <div class="col-lg-8">
        <input type="text" class="form-control" value="{{ $data->numeroid }}" readonly>
        <small class="form-text text-muted">Clave en Informix (<code>inumeroid</code>). Se asigna automáticamente al crear.</small>
    </div>
</div>
@endif
<div class="form-group row">
    <label for="nrodocumento" class="col-lg-3 col-form-label">Nro. documento</label>
    <div class="col-lg-8">
        <input type="text" name="nrodocumento" id="nrodocumento" class="form-control" value="{{ old('nrodocumento', $data->nrodocumento ?? '') }}" maxlength="20"/>
    </div>
</div>
<div class="form-group row">
    <label for="apellido" class="col-lg-3 col-form-label requerido">Apellido</label>
    <div class="col-lg-8">
        <input type="text" name="apellido" id="apellido" class="form-control" value="{{ old('apellido', $data->apellido ?? '') }}" required maxlength="40"/>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="40"/>
    </div>
</div>
<div class="form-group row">
    <label for="nickname" class="col-lg-3 col-form-label">Nickname</label>
    <div class="col-lg-8">
        <input type="text" name="nickname" id="nickname" class="form-control" value="{{ old('nickname', $data->nickname ?? '') }}" maxlength="30"/>
    </div>
</div>
<div class="form-group row">
    <label for="localidad" class="col-lg-3 col-form-label">Localidad</label>
    <div class="col-lg-8">
        <input type="text" name="localidad" id="localidad" class="form-control" value="{{ old('localidad', $data->localidad ?? '') }}" maxlength="15"/>
    </div>
</div>
