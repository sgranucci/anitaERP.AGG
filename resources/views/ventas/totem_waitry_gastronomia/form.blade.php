@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label for="ubicacion_id" class="col-lg-3 col-form-label requerido">Ubicación</label>
    <div class="col-lg-8">
        <select name="ubicacion_id" id="ubicacion_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($ubicacion_query as $ubicacion)
                <option value="{{ $ubicacion->id }}" {{ (int) old('ubicacion_id', $data->ubicacion_id ?? 0) === (int) $ubicacion->id ? 'selected' : '' }}>
                    {{ $ubicacion->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Salón o sector donde está instalado el tótem Waitry.</small>
    </div>
</div>
<div class="form-group row">
    <label for="waitry_table_id" class="col-lg-3 col-form-label">Table ID Waitry</label>
    <div class="col-lg-8">
        <input type="number" name="waitry_table_id" id="waitry_table_id" class="form-control"
               min="1" step="1"
               value="{{ old('waitry_table_id', $data->waitry_table_id ?? '') }}">
        <small class="form-text text-muted">
            ID de mesa/tótem en Waitry (<code>table.tableId</code> de la orden). Obligatorio si hay varios tótems en la empresa; si hay uno solo, puede dejarse vacío.
        </small>
    </div>
</div>
<div class="form-group row">
    <label for="detalle" class="col-lg-3 col-form-label">Detalle</label>
    <div class="col-lg-8">
        <textarea name="detalle" id="detalle" class="form-control" rows="3" maxlength="2000">{{ old('detalle', $data->detalle ?? '') }}</textarea>
        <small class="form-text text-muted">Comentario opcional (ej. número de serie, referencia física, observaciones).</small>
    </div>
</div>
