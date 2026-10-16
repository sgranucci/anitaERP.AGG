@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label for="servicio" class="col-lg-3 col-form-label requerido">Servicio</label>
    <div class="col-lg-8">
        <input type="text" name="servicio" id="servicio" class="form-control" maxlength="40" required
            value="{{ old('servicio', $data->servicio ?? '') }}" placeholder="Mostrador, VIP, Mood"/>
        <small class="form-text text-muted">El nombre del servicio en Waitry. Mood se puede cargar cuando exista el tableId.</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Mesa</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="80" required
            value="{{ old('nombre', $data->nombre ?? 'Mesa 1') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="table_id" class="col-lg-3 col-form-label requerido">tableId Waitry</label>
    <div class="col-lg-8">
        <input type="number" name="table_id" id="table_id" class="form-control" min="1" required
            value="{{ old('table_id', $data->table_id ?? '') }}"/>
        <small class="form-text text-muted">El id que Waitry indicó para esa mesa. La comanda manda solo este campo.</small>
    </div>
</div>
<div class="form-group row">
    <label for="activo" class="col-lg-3 col-form-label requerido">Activa</label>
    <div class="col-lg-8">
        <select name="activo" id="activo" class="form-control" required>
            <option value="1" {{ (int) old('activo', ($data->activo ?? true) ? 1 : 0) === 1 ? 'selected' : '' }}>Sí</option>
            <option value="0" {{ (int) old('activo', ($data->activo ?? true) ? 1 : 0) === 0 ? 'selected' : '' }}>No</option>
        </select>
    </div>
</div>
