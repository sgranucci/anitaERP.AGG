<div class="form-group row">
    <label for="identificador_pc" class="col-lg-3 col-form-label requerido">Identificador de PC</label>
    <div class="col-lg-8">
        <input type="text" name="identificador_pc" id="identificador_pc" class="form-control"
            value="{{ old('identificador_pc', ! empty($data->id) ? $data->identificador_pc : \App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc::sugerirEnFormularioAlta(request())) }}" required maxlength="100"/>
        <small class="form-text text-muted">IP, hostname o código único de la terminal. Al crear desde el navegador de la caja se sugiere la IP detectada por el servidor. En operación, con <code>ESTACIONAMIENTO_IDENTIFICADOR_USAR_IP_CLIENTE=true</code>, debe coincidir con esa IP.</small>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label">Descripción</label>
    <div class="col-lg-8">
        <input type="text" name="descripcion" id="descripcion" class="form-control"
            value="{{ old('descripcion', $data->descripcion ?? '') }}" maxlength="255"/>
        <small class="form-text text-muted">Opcional: nombre amigable (ej. Caja playa, Terminal acceso).</small>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'solo_lectura' => ! empty($data->id),
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label for="puntoventa_cae_id" class="col-lg-3 col-form-label requerido">Punto de venta CAE</label>
    <div class="col-lg-8">
        <select name="puntoventa_cae_id" id="puntoventa_cae_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($puntoventa_cae_query as $pv)
                <option value="{{ $pv->id }}" {{ (int) old('puntoventa_cae_id', $data->puntoventa_cae_id ?? 0) === (int) $pv->id ? 'selected' : '' }}>
                    {{ $pv->codigo }} — {{ $pv->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="puntoventa_caea_id" class="col-lg-3 col-form-label requerido">Punto de venta CAEA</label>
    <div class="col-lg-8">
        <select name="puntoventa_caea_id" id="puntoventa_caea_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($puntoventa_caea_query as $pv)
                <option value="{{ $pv->id }}" {{ (int) old('puntoventa_caea_id', $data->puntoventa_caea_id ?? 0) === (int) $pv->id ? 'selected' : '' }}>
                    {{ $pv->codigo }} — {{ $pv->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="tipotransaccion_id" class="col-lg-3 col-form-label requerido">Tipo de transacción (factura)</label>
    <div class="col-lg-8">
        <select name="tipotransaccion_id" id="tipotransaccion_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($tipotransaccion_query as $tt)
                <option value="{{ $tt->id }}" {{ (int) old('tipotransaccion_id', $data->tipotransaccion_id ?? 0) === (int) $tt->id ? 'selected' : '' }}>
                    {{ $tt->abreviatura }} — {{ $tt->nombre }} ({{ $tt->codigo }})
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Comprobante de venta que emite el POS al facturar estacionamiento.</small>
    </div>
</div>
<div class="form-group row">
    <label for="tipotransaccion_nota_credito_id" class="col-lg-3 col-form-label">Tipo de transacción (nota de crédito)</label>
    <div class="col-lg-8">
        <select name="tipotransaccion_nota_credito_id" id="tipotransaccion_nota_credito_id" class="form-control">
            <option value="">— Sin definir —</option>
            @foreach ($tipotransaccion_nota_credito_query as $tt)
                <option value="{{ $tt->id }}" {{ (int) old('tipotransaccion_nota_credito_id', $data->tipotransaccion_nota_credito_id ?? 0) === (int) $tt->id ? 'selected' : '' }}>
                    {{ $tt->abreviatura }} — {{ $tt->nombre }} ({{ $tt->codigo }})
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Opcional: usado al generar notas de crédito desde el módulo de estacionamiento.</small>
    </div>
</div>
<div class="form-group row">
    <label for="tipotransaccion_caja_id" class="col-lg-3 col-form-label requerido">Tipo de transacción de caja (cobranza)</label>
    <div class="col-lg-8">
        <select name="tipotransaccion_caja_id" id="tipotransaccion_caja_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($tipotransaccion_caja_query as $ttc)
                <option value="{{ $ttc->id }}" {{ (int) old('tipotransaccion_caja_id', $data->tipotransaccion_caja_id ?? 0) === (int) $ttc->id ? 'selected' : '' }}>
                    {{ $ttc->abreviatura }} — {{ $ttc->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Movimiento de caja que genera el POS al cobrar.</small>
    </div>
</div>
<div class="form-group row">
    <label for="salida_factura_id" class="col-lg-3 col-form-label requerido">Salida de factura electrónica</label>
    <div class="col-lg-8">
        <select name="salida_factura_id" id="salida_factura_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($salida_query as $salida)
                <option value="{{ $salida->id }}" {{ (int) old('salida_factura_id', $data->salida_factura_id ?? 0) === (int) $salida->id ? 'selected' : '' }}>
                    {{ $salida->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            Única salida del módulo: impresión de la factura electrónica (comando en Configuración → Salidas).
            El PDF queda disponible en el historial de facturación.
        </small>
    </div>
</div>
