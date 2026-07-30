@php
    $data = $data ?? null;
@endphp
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required
            value="{{ old('nombre', $data->nombre ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">Descripción</label>
    <div class="col-lg-8">
        <input type="text" name="descripcion" class="form-control" maxlength="255"
            value="{{ old('descripcion', $data->descripcion ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right requerido">Impuesto</label>
    <div class="col-lg-2">
        <input type="number" name="codigo_impuesto" class="form-control" required readonly
            value="{{ old('codigo_impuesto', $data->codigo_impuesto ?? 353) }}">
        <small class="form-text text-muted">Fijo 353 (F2004).</small>
    </div>
    <label class="col-lg-2 col-form-label text-right">Régimen default</label>
    <div class="col-lg-2">
        <input type="number" name="codigo_regimen" class="form-control" min="1" max="999"
            value="{{ old('codigo_regimen', $data->codigo_regimen ?? '') }}"
            placeholder="Opcional">
        <small class="form-text text-muted">Si el movimiento no trae régimen.</small>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right requerido">Frecuencia</label>
    <div class="col-lg-3">
        <select name="frecuencia" class="form-control" required>
            @foreach ($frecuencia_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('frecuencia', $data->frecuencia ?? 'quincenal') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-5">
        <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                @checked(old('activo', $data->activo ?? true))>
            <label class="custom-control-label" for="activo">Activo</label>
        </div>
    </div>
</div>

<hr>
<h5>Cuentas contables por empresa (conciliación mayor)</h5>
<p class="text-muted small">Cuenta típica de retención SUSS: <strong>214010015</strong>. Puede asignar más de una por empresa.</p>
<table class="table table-sm" id="cuentacontable-table">
    <thead>
        <tr>
            <th>Empresa</th>
            <th>Cuenta contable</th>
            <th></th>
        </tr>
    </thead>
    <tbody id="tbody-cuentacontable-table">
        @php
            $cuentasRows = old('cuentacontable_ids')
                ? collect(old('cuentacontable_ids'))->map(fn ($id, $i) => (object) [
                    'empresa_id' => old('empresa_ids')[$i] ?? null,
                    'cuentacontable_id' => $id,
                    'cuentacontable' => null,
                ])
                : ($data->cuentas ?? collect());
        @endphp
        @forelse ($cuentasRows as $cuentacontable)
            <tr class="item-cuentacontable">
                <td>
                    @include('includes.form-empresa-asignada-control', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $cuentacontable->empresa_id ?? ($cuentacontable->cuentacontable->empresa_id ?? null),
                        'name' => 'empresa_ids[]',
                        'select_class' => 'empresa',
                        'permite_vacio' => true,
                        'opcion_vacia' => '-- Seleccionar --',
                    ])
                </td>
                <td>
                    <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]"
                        value="{{ $cuentacontable->cuentacontable_id ?? '' }}">
                    <button type="button" class="btn-accion-tabla consultacuentacontable tooltipsC" title="Consulta cuentas">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                    <input type="text" style="width:120px;display:inline-block" class="codigocuentacontable form-control d-inline-block"
                        value="{{ $cuentacontable->cuentacontable->codigo ?? '' }}">
                    <input type="text" style="width:280px;display:inline-block" class="nombrecuentacontable form-control d-inline-block" readonly
                        value="{{ $cuentacontable->cuentacontable->nombre ?? '' }}">
                </td>
                <td>
                    <button type="button" class="btn-accion-tabla eliminar_cuentacontable tooltipsC">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                </td>
            </tr>
        @empty
            <tr class="item-cuentacontable">
                <td>
                    @include('includes.form-empresa-asignada-control', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => null,
                        'name' => 'empresa_ids[]',
                        'select_class' => 'empresa',
                        'permite_vacio' => true,
                        'opcion_vacia' => '-- Seleccionar --',
                    ])
                </td>
                <td>
                    <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="">
                    <button type="button" class="btn-accion-tabla consultacuentacontable tooltipsC" title="Consulta cuentas">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                    <input type="text" style="width:120px;display:inline-block" class="codigocuentacontable form-control d-inline-block">
                    <input type="text" style="width:280px;display:inline-block" class="nombrecuentacontable form-control d-inline-block" readonly>
                </td>
                <td>
                    <button type="button" class="btn-accion-tabla eliminar_cuentacontable tooltipsC">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
<button type="button" class="btn btn-sm btn-outline-primary" id="agrega_renglon_cuentacontable">
    <i class="fa fa-plus"></i> Agregar cuenta
</button>
