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
    <label for="waitry_layout_id" class="col-lg-3 col-form-label">Layout ID Waitry</label>
    <div class="col-lg-8">
        <input type="number" name="waitry_layout_id" id="waitry_layout_id" class="form-control"
               min="1" step="1"
               value="{{ old('waitry_layout_id', $data->waitry_layout_id ?? '') }}">
        <small class="form-text text-muted">
            Punto de acceso Waitry (<code>table.layout.id</code> en getOrdersPOS; ej. Kiosco 1 → <code>32392</code>).
            Prioridad al repartir cierre e Informe Z. Recomendado para kioscos y salones con varias mesas.
        </small>
    </div>
</div>
<div class="form-group row">
    <label for="waitry_layout_ids_adicionales" class="col-lg-3 col-form-label">Layout ID adicionales</label>
    <div class="col-lg-8">
        <input type="text" name="waitry_layout_ids_adicionales" id="waitry_layout_ids_adicionales" class="form-control"
               maxlength="255"
               placeholder="32393, 32394"
               value="{{ old('waitry_layout_ids_adicionales', $data->waitry_layout_ids_adicionales ?? '') }}">
        <small class="form-text text-muted">
            Otros puntos de acceso (<code>table.layout.id</code>) del mismo tótem físico, separados por coma
            (ej. segundo kiosco Pizzería → <code>32393</code>).
        </small>
    </div>
</div>
<div class="form-group row">
    <label for="waitry_table_id" class="col-lg-3 col-form-label">Table ID Waitry</label>
    <div class="col-lg-8">
        <input type="number" name="waitry_table_id" id="waitry_table_id" class="form-control"
               min="1" step="1"
               value="{{ old('waitry_table_id', $data->waitry_table_id ?? '') }}">
        <small class="form-text text-muted">
            Mesa concreta (<code>table.id</code>; ej. K1 → <code>103443</code>). Opcional si ya cargó Layout ID; útil para afinar o legacy sin layout.
        </small>
    </div>
</div>
<div class="form-group row">
    <label for="waitry_table_ids_adicionales" class="col-lg-3 col-form-label">Table ID adicionales</label>
    <div class="col-lg-8">
        <input type="text" name="waitry_table_ids_adicionales" id="waitry_table_ids_adicionales" class="form-control"
               maxlength="255"
               placeholder="103444, 103445"
               value="{{ old('waitry_table_ids_adicionales', $data->waitry_table_ids_adicionales ?? '') }}">
        <small class="form-text text-muted">
            Otros <code>tableId</code> del mismo tótem físico, separados por coma (ej. segundo POS en el mismo salón).
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
<div class="form-group row">
    <label for="informe_z_habilitado" class="col-lg-3 col-form-label">Informe Z Posnet</label>
    <div class="col-lg-8">
        <input type="hidden" name="informe_z_habilitado" value="0">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="informe_z_habilitado" id="informe_z_habilitado"
                   value="1" {{ old('informe_z_habilitado', $data->informe_z_habilitado ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="informe_z_habilitado">
                Incluir en plantilla Informe Z (conciliación Posnet kiosco Waitry)
            </label>
        </div>
        <small class="form-text text-muted">
            Desmarque si el tótem solo aporta al cierre operativo de gastronomía (ej. Tomasso con push Anita).
            Sigue sumando en el resumen operativo del cierre tótem y caja.
        </small>
    </div>
</div>
