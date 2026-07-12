<div class="form-group row">
    <label for="identificador_pc" class="col-lg-3 col-form-label requerido">Identificador de PC</label>
    <div class="col-lg-8">
        <input type="text" name="identificador_pc" id="identificador_pc" class="form-control"
            value="{{ old('identificador_pc', $data->exists ? $data->identificador_pc : \App\Support\Ventas\GastronomiaIdentificadorPc::resolver(request())) }}" required maxlength="100"/>
        <small class="form-text text-muted">IP, hostname o código único de la terminal de viandas. El proceso resuelve la empresa y depósitos desde esta fila (una configuración por PC).</small>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="descripcion" id="descripcion" class="form-control"
            value="{{ old('descripcion', $data->descripcion ?? '') }}" maxlength="255"/>
        <small class="form-text text-muted">Nombre amigable de la terminal (ej. Comedor planta, Terminal viandas RRHH).</small>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'solo_lectura' => ! empty($data->id),
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label for="ubicacion_id" class="col-lg-3 col-form-label">Ubicación</label>
    <div class="col-lg-8">
        <select name="ubicacion_id" id="ubicacion_id" class="form-control">
            <option value="">— Sin ubicación —</option>
            @foreach ($ubicacion_query as $ubicacion)
                <option value="{{ $ubicacion->id }}" {{ (int) old('ubicacion_id', $data->ubicacion_id ?? 0) === (int) $ubicacion->id ? 'selected' : '' }}>
                    {{ $ubicacion->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Ubicación donde está físicamente la terminal (ubicaciones de gastronomía, filtradas por la empresa elegida).</small>
    </div>
</div>
<div class="form-group row">
    <label for="deposito_platos_id" class="col-lg-3 col-form-label requerido">Depósito descuento de platos</label>
    <div class="col-lg-8">
        <select name="deposito_platos_id" id="deposito_platos_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($deposito_query as $dep)
                <option value="{{ $dep->id }}" {{ (int) old('deposito_platos_id', $data->deposito_platos_id ?? config('facturacion.DEPOSITO_VENTA_ID')) === (int) $dep->id ? 'selected' : '' }}>
                    {{ $dep->codigo }} — {{ $dep->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Depósito del que se descuenta el artículo (plato/menú) que se marcha en la comanda.</small>
    </div>
</div>
<div class="form-group row">
    <label for="deposito_insumos_id" class="col-lg-3 col-form-label requerido">Depósito descuento de insumos</label>
    <div class="col-lg-8">
        <select name="deposito_insumos_id" id="deposito_insumos_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($deposito_query as $dep)
                <option value="{{ $dep->id }}" {{ (int) old('deposito_insumos_id', $data->deposito_insumos_id ?? config('facturacion.DEPOSITO_VENTA_ID')) === (int) $dep->id ? 'selected' : '' }}>
                    {{ $dep->codigo }} — {{ $dep->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Depósito del que se descuentan los ingredientes al explotar la fórmula del plato marchado.</small>
    </div>
</div>
<div class="form-group row">
    <label for="tipotransaccion_stock_id" class="col-lg-3 col-form-label requerido">Tipo de transacción de stock (descuento)</label>
    <div class="col-lg-8">
        <select name="tipotransaccion_stock_id" id="tipotransaccion_stock_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($tipotransaccion_query as $tt)
                <option value="{{ $tt->id }}" {{ (int) old('tipotransaccion_stock_id', $data->tipotransaccion_stock_id ?? 0) === (int) $tt->id ? 'selected' : '' }}>
                    {{ $tt->abreviatura }} — {{ $tt->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Movimiento de stock que registra el consumo. Sólo se listan tipos de transacción de stock con operación «Salida» (descuentan stock).</small>
    </div>
</div>
<div class="form-group row">
    <label for="listaprecio_venta_id" class="col-lg-3 col-form-label">Lista de precios de venta</label>
    <div class="col-lg-8">
        <select name="listaprecio_venta_id" id="listaprecio_venta_id" class="form-control">
            <option value="">— Sin lista de venta —</option>
            @foreach ($listaprecio_query as $lp)
                <option value="{{ $lp->id }}" {{ (int) old('listaprecio_venta_id', $data->listaprecio_venta_id ?? 0) === (int) $lp->id ? 'selected' : '' }}>
                    {{ $lp->nombre }} @if ($lp->codigo)({{ $lp->codigo }})@endif
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            Precio de venta / valor al empleado al marchar la vianda. El <strong>costo</strong> se toma automáticamente de la lista
            {{ (int) config('vianda.costo_lista_base', 5000) }}+mes (ej. julio → {{ (int) config('vianda.costo_lista_base', 5000) + 7 }}),
            recalculada diariamente con <code>gastronomia:actualizar-costo-mensual-catalogo</code> (inicio de jornada).
        </small>
    </div>
</div>
<div class="form-group row">
    <label for="salida_voucher_id" class="col-lg-3 col-form-label requerido">Impresora / salida del voucher</label>
    <div class="col-lg-8">
        <select name="salida_voucher_id" id="salida_voucher_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($salida_query as $salida)
                <option value="{{ $salida->id }}" {{ (int) old('salida_voucher_id', $data->salida_voucher_id ?? 0) === (int) $salida->id ? 'selected' : '' }}>
                    {{ $salida->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            Impresora térmica donde se emite el voucher interno de retiro (comando en Configuración → Salidas, con <code>%s</code> = archivo ESC/POS).
        </small>
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <div class="col-lg-8">
        <select name="estado" id="estado" class="form-control" required>
            <option value="A" {{ old('estado', $data->estado ?? 'A') === 'A' ? 'selected' : '' }}>Activo</option>
            <option value="I" {{ old('estado', $data->estado ?? 'A') === 'I' ? 'selected' : '' }}>Inactivo</option>
        </select>
    </div>
</div>
