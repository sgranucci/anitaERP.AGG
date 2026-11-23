{{-- Consulta rápida original (Proveedor/texto + Nº) — todo en un renglón. --}}
<div class="lw-filtros-busqueda card-body py-2 border-bottom bg-light">
    <div class="lw-busqueda-fila d-flex flex-wrap align-items-end">
        <div class="lw-busqueda-campo lw-busqueda-texto">
            <label class="small mb-1" for="filtro_valor">Proveedor o texto</label>
            <input type="text" name="filtro_valor" id="filtro_valor" class="form-control form-control-sm"
                   value="{{ $filtros['valor'] ?? '' }}"
                   placeholder="Texto (tolera errores de tipeo desde 6 caracteres)"
                   autocomplete="off"
                   title="Busca en el nombre del proveedor y en el resto de los datos del legajo">
        </div>
        <div class="lw-busqueda-campo">
            <label class="small mb-1" for="nro_oc">Nº OC</label>
            <input type="text" name="nro_oc" id="nro_oc" class="form-control form-control-sm"
                   value="{{ $filtros['nro_oc'] ?? '' }}" placeholder="Orden de compra" autocomplete="off">
        </div>
        <div class="lw-busqueda-campo">
            <label class="small mb-1" for="nro_factura">Nº factura</label>
            <input type="text" name="nro_factura" id="nro_factura" class="form-control form-control-sm"
                   value="{{ $filtros['nro_factura'] ?? '' }}" placeholder="Número o dígitos" autocomplete="off">
        </div>
        <div class="lw-busqueda-campo">
            <label class="small mb-1" for="nro_com">Nº COM</label>
            <input type="text" name="nro_com" id="nro_com" class="form-control form-control-sm"
                   value="{{ $filtros['nro_com'] ?? '' }}" placeholder="Número o ID" autocomplete="off">
        </div>
        <div class="lw-busqueda-campo">
            <label class="small mb-1" for="nro_op">Nº orden de pago</label>
            <input type="text" name="nro_op" id="nro_op" class="form-control form-control-sm"
                   value="{{ $filtros['nro_op'] ?? '' }}" placeholder="Cuando el legajo está pago" autocomplete="off">
        </div>
        <div class="lw-busqueda-acciones">
            <button type="submit" class="btn btn-primary btn-sm" id="btn-lw-buscar-nros">
                <i class="fa fa-search"></i> Buscar
            </button>
            <a href="{{ $limpiarUrl }}" class="btn btn-outline-secondary btn-sm" title="Limpiar proveedor, números, atajo y QBE">
                <i class="fa fa-eraser"></i> Limpiar
            </a>
        </div>
    </div>
</div>
