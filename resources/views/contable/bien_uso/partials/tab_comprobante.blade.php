@php
    $tieneContable = \Illuminate\Support\Facades\Schema::hasColumn('bien_uso', 'proveedor_id');
    $prov = optional($data->proveedor ?? null);
@endphp

@if (! $tieneContable)
    <p class="text-muted mb-0">Pendiente migraci&oacute;n de campos contables (comprobante de compra).</p>
@else
@include('compras.partials.campo_consulta_proveedor', [
    'prefix' => 'bien',
    'proveedorId' => old('proveedor_id', $data->proveedor_id ?? ''),
    'codigo' => old('proveedor_codigo', $prov->codigo ?? ''),
    'nombre' => old('proveedor_nombre', $prov->nombre ?? ''),
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-7',
])

<div class="form-group row">
    <label for="marca" class="col-lg-4 control-label text-right pr-2">Marca</label>
    <div class="col-lg-4">
        <input type="text" name="marca" id="marca" class="form-control" maxlength="20"
               value="{{ old('marca', $data->marca ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="tipo_factura" class="col-lg-4 control-label text-right pr-2">Tipo / letra</label>
    <div class="col-lg-2">
        <input type="text" name="tipo_factura" id="tipo_factura" class="form-control" maxlength="3"
               value="{{ old('tipo_factura', $data->tipo_factura ?? '') }}" placeholder="FAC">
    </div>
    <div class="col-lg-1">
        <input type="text" name="letra_factura" id="letra_factura" class="form-control" maxlength="1"
               value="{{ old('letra_factura', $data->letra_factura ?? '') }}" placeholder="A">
    </div>
</div>

<div class="form-group row">
    <label for="sucursal_factura" class="col-lg-4 control-label text-right pr-2">Sucursal / nro</label>
    <div class="col-lg-2">
        <input type="number" name="sucursal_factura" id="sucursal_factura" class="form-control" min="0"
               value="{{ old('sucursal_factura', $data->sucursal_factura ?? '') }}">
    </div>
    <div class="col-lg-3">
        <input type="number" name="nro_factura" id="nro_factura" class="form-control" min="0"
               value="{{ old('nro_factura', $data->nro_factura ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="monto_factura" class="col-lg-4 control-label text-right pr-2">Monto factura</label>
    <div class="col-lg-3">
        <input type="number" step="0.01" name="monto_factura" id="monto_factura" class="form-control"
               value="{{ old('monto_factura', $data->monto_factura ?? '0') }}">
    </div>
    <label for="fecha_factura" class="col-lg-2 control-label text-right pr-2">Fecha</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_factura" id="fecha_factura" class="form-control"
               value="{{ old('fecha_factura', optional($data->fecha_factura ?? null)->format('Y-m-d') ?? '') }}">
    </div>
</div>
@endif
