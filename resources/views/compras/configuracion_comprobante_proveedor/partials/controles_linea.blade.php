@php
    $cfgSku = (bool) old('controla_sku_vs_com', $config->controla_sku_vs_com ?? false);
    $cfgPrecio = (bool) old('controla_precio_unitario', $config->controla_precio_unitario ?? false);
    $tolPrecio = old('tolerancia_precio_pct', $config->tolerancia_precio_pct ?? 0);
@endphp

<div class="card card-outline card-secondary mb-4" id="cp-controles-linea">
    <div class="card-header py-2">
        <h5 class="mb-0"><i class="fa fa-barcode"></i> Controles por línea (SKU / precio)</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Opcionales y <strong>desactivados por defecto</strong>. AGG sigue solo con el control de
            <em>importe factura vs provisión COM</em> (tolerancias por centro de costo).
            El servicio de match (SKU ↔ COM/OC + precio unitario) ya está implementado;
            al activarlos se usan las líneas de <code>comprobante_proveedor_articulo</code>
            (o arrays del form / payload IA <code>articulos</code>|<code>lineas_articulo</code>).
        </p>

        <div class="form-group row mb-2">
            <label class="col-lg-3 control-label text-right pr-2"></label>
            <div class="col-lg-8">
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="controla_sku_vs_com" value="0">
                    <input type="checkbox" class="custom-control-input" id="controla_sku_vs_com"
                           name="controla_sku_vs_com" value="1" @checked($cfgSku)>
                    <label class="custom-control-label" for="controla_sku_vs_com">
                        Controlar SKU factura vs COM/OC
                    </label>
                </div>
                <p class="text-muted small mb-0 ml-4">
                    Avisa o bloquea si hay SKU en factura sin recepción / OC, o COM sin correlato en factura.
                </p>
            </div>
        </div>

        <div class="form-group row mb-2">
            <label class="col-lg-3 control-label text-right pr-2"></label>
            <div class="col-lg-8">
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="controla_precio_unitario" value="0">
                    <input type="checkbox" class="custom-control-input" id="controla_precio_unitario"
                           name="controla_precio_unitario" value="1" @checked($cfgPrecio)>
                    <label class="custom-control-label" for="controla_precio_unitario">
                        Controlar precio unitario vs COM/OC
                    </label>
                </div>
                <p class="text-muted small mb-0 ml-4">
                    Por SKU emparejado, compara precio unitario de factura contra COM (o OC).
                    Fuera de tolerancia: mismo criterio que importe (devolver legajo a Compras en modo estricto).
                </p>
            </div>
        </div>

        <div class="form-group row mb-0">
            <label for="tolerancia_precio_pct" class="col-lg-3 control-label text-right pr-2">
                Tolerancia precio %
            </label>
            <div class="col-lg-3">
                <input type="number" step="0.0001" min="0" max="100"
                       class="form-control" id="tolerancia_precio_pct" name="tolerancia_precio_pct"
                       value="{{ is_numeric($tolPrecio) ? $tolPrecio : 0 }}">
                <small class="form-text text-muted">Aplica si está activo el control de precio. 0 = sin holgura.</small>
            </div>
        </div>
    </div>
</div>
