<div class="form-group row">
    <label for="identificador_pc" class="col-lg-3 col-form-label requerido">Identificador de PC</label>
    <div class="col-lg-8">
        <input type="text" name="identificador_pc" id="identificador_pc" class="form-control"
            value="{{ old('identificador_pc', $data->exists ? $data->identificador_pc : \App\Support\Stock\GastronomiaIdentificadorPc::resolver(request())) }}" required maxlength="100"/>
        <small class="form-text text-muted">IP, hostname o código único de la estación (clave por empresa). Con <code>GASTRONOMIA_IDENTIFICADOR_USAR_IP_CLIENTE=true</code> debe coincidir con la IP que ve el servidor para ese navegador.</small>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label">Descripción</label>
    <div class="col-lg-8">
        <input type="text" name="descripcion" id="descripcion" class="form-control"
            value="{{ old('descripcion', $data->descripcion ?? '') }}" maxlength="255"/>
        <small class="form-text text-muted">Opcional: nombre amigable (ej. Caja salón, Terminal cocina).</small>
    </div>
</div>
<div class="form-group row">
    <label for="empresa_id" class="col-lg-3 col-form-label requerido">Empresa</label>
    <div class="col-lg-8">
        <select name="empresa_id" id="empresa_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($empresa_query as $empresa)
                <option value="{{ $empresa->id }}" {{ (int) old('empresa_id', $data->empresa_id ?? config('cliente.EMPRESA_DEFAULT_ID')) === (int) $empresa->id ? 'selected' : '' }}>
                    {{ $empresa->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
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
    <label for="ubicacion_id" class="col-lg-3 col-form-label">Ubicación (filtro mesas)</label>
    <div class="col-lg-8">
        <select name="ubicacion_id" id="ubicacion_id" class="form-control">
            <option value="">Todas las ubicaciones</option>
            @foreach ($ubicacion_query as $ubicacion)
                <option value="{{ $ubicacion->id }}" {{ (int) old('ubicacion_id', $data->ubicacion_id ?? 0) === (int) $ubicacion->id ? 'selected' : '' }}>
                    {{ $ubicacion->nombre }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Si se define, en el terminal solo se listan mesas de esa ubicación.</small>
    </div>
</div>
<div class="form-group row">
    <label for="listaprecio_id" class="col-lg-3 col-form-label requerido">Lista de precios (POS gastronomía)</label>
    <div class="col-lg-8">
        <select name="listaprecio_id" id="listaprecio_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($listaprecio_query as $lp)
                <option value="{{ $lp->id }}" {{ (int) old('listaprecio_id', $data->listaprecio_id ?? 1) === (int) $lp->id ? 'selected' : '' }}>
                    {{ $lp->nombre }} @if ($lp->codigo)({{ $lp->codigo }})@endif
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Precios sugeridos del catálogo (SKU configurado) en el proceso de facturación gastronómía.</small>
    </div>
</div>
<div class="form-group row">
    <label for="salida_comanda_id" class="col-lg-3 col-form-label requerido">Salida de comandas</label>
    <div class="col-lg-8">
        <select name="salida_comanda_id" id="salida_comanda_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($salida_query as $salida)
                <option value="{{ $salida->id }}" {{ (int) old('salida_comanda_id', $data->salida_comanda_id ?? 0) === (int) $salida->id ? 'selected' : '' }}>
                    {{ $salida->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="salida_factura_id" class="col-lg-3 col-form-label requerido">Salida de facturas</label>
    <div class="col-lg-8">
        <select name="salida_factura_id" id="salida_factura_id" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($salida_query as $salida)
                <option value="{{ $salida->id }}" {{ (int) old('salida_factura_id', $data->salida_factura_id ?? 0) === (int) $salida->id ? 'selected' : '' }}>
                    {{ $salida->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
