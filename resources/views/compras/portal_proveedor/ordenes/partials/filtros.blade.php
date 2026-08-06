<form method="get" action="{{ route('portal_proveedores_ordenes') }}" class="mb-3" id="form-filtros-portal-ordenes">
    <input type="hidden" name="proveedor_id" value="{{ $proveedorId }}">
    <input type="hidden" name="consultar" value="1">
    <div class="form-row">
        <div class="col-md-2">
            <label>Empresa</label>
            <select name="empresa_id" class="form-control form-control-sm">
                <option value="">Todas</option>
                @foreach ($empresa_query as $e)
                    <option value="{{ $e->id }}" @selected(($filtros['empresa_id'] ?? null) == $e->id)>
                        {{ $e->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label>Vista</label>
            <select name="grupo_estado" class="form-control form-control-sm">
                @foreach (\App\Support\Compras\PortalProveedorOrdencompraListadoFiltros::OPCIONES_GRUPO as $op)
                    <option value="{{ $op['valor'] }}" @selected(($filtros['grupo_estado'] ?? 'activas') === $op['valor'])>
                        {{ $op['etiqueta'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label>Estado OC</label>
            <select name="estadoordencompra" class="form-control form-control-sm">
                <option value="">Todos del grupo</option>
                @foreach (\App\Support\Compras\OrdencompraEstados::todos() as $est)
                    <option value="{{ $est }}" @selected(($filtros['estadoordencompra'] ?? '') === $est)>
                        {{ $est }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <label>Nº OC</label>
            <input type="text" name="numero" class="form-control form-control-sm"
                   value="{{ $filtros['numero'] ?? '' }}" placeholder="Número">
        </div>
        <div class="col-md-2">
            <label>Desde</label>
            <input type="date" name="fecha_desde" class="form-control form-control-sm"
                   value="{{ $filtros['fecha_desde'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label>Hasta</label>
            <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm mr-1" title="Consultar">
                <i class="fa fa-search"></i>
            </button>
            <a href="{{ route('portal_proveedores_ordenes', ['proveedor_id' => $proveedorId]) }}"
               class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                <i class="fa fa-times"></i>
            </a>
        </div>
    </div>
</form>
