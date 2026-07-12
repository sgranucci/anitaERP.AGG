@php
    $data = $data ?? null;
@endphp
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right requerido">Código impuesto SICORE</label>
    <div class="col-lg-3">
        <input type="number" name="codigo_impuesto" id="codigo_impuesto" class="form-control" min="1" max="9999" required
            value="{{ old('codigo_impuesto', $data->codigo_impuesto ?? '') }}"
            placeholder="217 / 767 / 787">
        <small class="form-text text-muted">Cód. ARCA del impuesto (ej. 217 ganancias, 767 IVA, 787 sueldos).</small>
    </div>
    <label class="col-lg-2 col-form-label text-right">Código régimen</label>
    <div class="col-lg-3">
        <input type="number" name="codigo_regimen" id="codigo_regimen" class="form-control" min="0" max="999"
            value="{{ old('codigo_regimen', $data->codigo_regimen ?? '') }}"
            placeholder="493 ventas · 160 sueldos · vacío compras">
        <small class="form-text text-muted">Fijo ventas/sueldos; compras lo toma de retencionganancia/retencioniva si está vacío.</small>
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
    <label class="col-lg-3 col-form-label text-right requerido">Criterio / origen</label>
    <div class="col-lg-4">
        <select name="criterio" id="criterio" class="form-control" required>
            @foreach ($criterio_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('criterio', $data->criterio ?? '') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <label class="col-lg-2 col-form-label text-right requerido">Operación</label>
    <div class="col-lg-2">
        <select name="codigo_operacion" id="codigo_operacion" class="form-control" required>
            <option value="1" @selected((int) old('codigo_operacion', $data->codigo_operacion ?? 1) === 1)>1 Retención</option>
            <option value="2" @selected((int) old('codigo_operacion', $data->codigo_operacion ?? 1) === 2)>2 Percepción</option>
        </select>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right requerido">Concilia con</label>
    <div class="col-lg-3">
        <select name="concilia_con" class="form-control" required>
            @foreach ($concilia_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('concilia_con', $data->concilia_con ?? 'sicore') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <label class="col-lg-2 col-form-label text-right requerido">Frecuencia</label>
    <div class="col-lg-3">
        <select name="frecuencia" class="form-control" required>
            @foreach ($frecuencia_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('frecuencia', $data->frecuencia ?? 'quincenal') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">Quincena 1 (días)</label>
    <div class="col-lg-2">
        <input type="number" name="quincena_1_desde" class="form-control" min="1" max="31"
            value="{{ old('quincena_1_desde', $data->quincena_1_desde ?? 1) }}">
    </div>
    <div class="col-lg-1 text-center pt-2">al</div>
    <div class="col-lg-2">
        <input type="number" name="quincena_1_hasta" class="form-control" min="1" max="31"
            value="{{ old('quincena_1_hasta', $data->quincena_1_hasta ?? 15) }}">
    </div>
    <label class="col-lg-1 col-form-label text-right">Q2</label>
    <div class="col-lg-1">
        <input type="number" name="quincena_2_desde" class="form-control" min="1" max="31"
            value="{{ old('quincena_2_desde', $data->quincena_2_desde ?? 16) }}">
    </div>
    <div class="col-lg-1 text-center pt-2">al</div>
    <div class="col-lg-1">
        <input type="number" name="quincena_2_hasta" class="form-control" min="1" max="31"
            value="{{ old('quincena_2_hasta', $data->quincena_2_hasta ?? 31) }}">
    </div>
</div>
<div class="form-group row d-none" id="row-sueldos-conceptos">
    <label class="col-lg-3 col-form-label text-right requerido">Concepto retención (haberes)</label>
    <div class="col-lg-3">
        <input type="number" name="concepto_retencion_sueldos" id="concepto_retencion_sueldos" class="form-control"
            value="{{ old('concepto_retencion_sueldos', $data->concepto_retencion_sueldos ?? '') }}"
            placeholder="Cód. haberes Anita">
    </div>
    <label class="col-lg-3 col-form-label text-right">Concepto devolución</label>
    <div class="col-lg-3">
        <input type="number" name="concepto_devolucion_sueldos" id="concepto_devolucion_sueldos" class="form-control"
            value="{{ old('concepto_devolucion_sueldos', $data->concepto_devolucion_sueldos ?? '') }}"
            placeholder="Opcional">
    </div>
    <div class="col-lg-3"></div>
    <div class="col-lg-8">
        <p class="text-muted small mb-0">
            Ítem 787 (4ta categoría): indique el código de haberes de retención y, si aplica, el de devolución.
            Los importes se leen de <code>auxrec</code> / <code>auxhist</code> en Anita sueldos.
        </p>
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
<p class="text-muted small">Puede asignar más de una cuenta por empresa para el mismo código SICORE.</p>
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
