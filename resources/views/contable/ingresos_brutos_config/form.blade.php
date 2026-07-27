@php
    $data = $data ?? null;
    $provincia = $data->provincia ?? null;
@endphp
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right requerido">Provincia</label>
    <div class="col-lg-8">
        <input type="hidden" class="provincia_id" id="provincia_id" name="provincia_id"
            value="{{ old('provincia_id', $data->provincia_id ?? '') }}" required>
        <div class="input-group">
            <input type="text" class="form-control col-lg-2 codigoprovincia" id="codigoprovincia" name="codigoprovincia"
                value="{{ old('codigoprovincia', $provincia->codigo ?? '') }}"
                placeholder="Cód." autocomplete="off">
            <input type="text" class="form-control col-lg-5 nombreprovincia" id="nombreprovincia" name="nombreprovincia"
                value="{{ old('nombreprovincia', $provincia->nombre ?? '') }}" readonly
                placeholder="Nombre provincia">
            <div class="input-group-append">
                <button type="button" title="Consultar provincias" class="btn btn-outline-secondary consultaprovincia tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right requerido">Tipo</label>
    <div class="col-lg-4">
        <select name="tipo" id="tipo" class="form-control" required>
            @foreach ($tipo_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('tipo', $data->tipo ?? '') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <label class="col-lg-2 col-form-label text-right requerido">Frecuencia</label>
    <div class="col-lg-2">
        <select name="frecuencia" class="form-control" required>
            @foreach ($frecuencia_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('frecuencia', $data->frecuencia ?? 'quincenal') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
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
    <label class="col-lg-3 col-form-label text-right requerido">Código actividad ARBA</label>
    <div class="col-lg-3">
        <select name="codigo_actividad_arba" id="codigo_actividad_arba" class="form-control" required>
            <option value="6" @selected((int) old('codigo_actividad_arba', $data->codigo_actividad_arba ?? 6) === 6)>
                6 — Retenciones
            </option>
            <option value="7" @selected((int) old('codigo_actividad_arba', $data->codigo_actividad_arba ?? 6) === 7)>
                7 — Percepciones
            </option>
        </select>
        <small class="form-text text-muted">Actividad en el nombre de lote ER-… (Anita arma_lote_arba).</small>
    </div>
</div>
<div class="form-group row">
    <div class="col-lg-3"></div>
    <div class="col-lg-8">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                @checked(old('activo', $data->activo ?? true))>
            <label class="custom-control-label" for="activo">Activo</label>
        </div>
    </div>
</div>

<hr>
<h5>Cuentas contables por empresa (conciliación mayor)</h5>
<p class="text-muted small">Puede asignar más de una cuenta por empresa para la misma configuración IIBB.</p>
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
                    <input type="text" style="width:280px;display:inline-block" class="nombrecuentacontable form-control d-inline-block"
                        value="{{ $cuentacontable->cuentacontable->nombre ?? '' }}" readonly>
                </td>
                <td>
                    <button type="button" class="btn-accion-tabla eliminar_cuentacontable tooltipsC" title="Eliminar">
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
                    ])
                </td>
                <td>
                    <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="">
                    <button type="button" class="btn-accion-tabla consultacuentacontable tooltipsC">
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
<button type="button" id="agrega_renglon_cuentacontable" class="btn btn-outline-secondary btn-sm">+ Agregar cuenta</button>
