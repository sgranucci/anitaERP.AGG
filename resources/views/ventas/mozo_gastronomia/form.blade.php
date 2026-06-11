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
    <label for="clave" class="col-lg-3 col-form-label">Clave POS</label>
    <div class="col-lg-8">
        <input type="password" name="clave" id="clave" class="form-control" value="" maxlength="60" autocomplete="new-password"/>
        <small class="form-text text-muted">Clave para ingresar al facturador de canjes marketing. Dejar vacío al editar para no cambiar.</small>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'col_input' => 'col-lg-8',
])
