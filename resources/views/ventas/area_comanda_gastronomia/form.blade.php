<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-8">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" required maxlength="255"/>
        <small class="form-text text-muted">Identificador usado para el envío de la comanda (debe ser único dentro de la empresa).</small>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'col_input' => 'col-lg-8',
])
