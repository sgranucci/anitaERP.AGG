<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="legajo" class="col-lg-3 col-form-label">Legajo</label>
    <div class="col-lg-2">
        <input type="number" name="legajo" id="legajo" class="form-control" min="1" value="{{ old('legajo', $data->legajo ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="activo" class="col-lg-3 col-form-label requerido">Activo</label>
    <div class="col-lg-2">
        <select name="activo" id="activo" class="form-control" required>
            <option value="S" {{ old('activo', $data->activo ?? 'S') === 'S' ? 'selected' : '' }}>S&iacute;</option>
            <option value="N" {{ old('activo', $data->activo ?? 'S') === 'N' ? 'selected' : '' }}>No</option>
        </select>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'col_input' => 'col-lg-4',
])
