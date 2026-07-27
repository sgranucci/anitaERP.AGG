<form method="get" action="{{ route('portal_proveedores_retenciones') }}" class="mb-3" id="form-filtros-portal-retenciones">
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
            <label>Tipo</label>
            <select name="tiporetencion" class="form-control form-control-sm">
                <option value="">Todas</option>
                <option value="G" @selected(($filtros['tiporetencion'] ?? '') === 'G')>Ganancias</option>
                <option value="I" @selected(($filtros['tiporetencion'] ?? '') === 'I')>IVA</option>
                <option value="S" @selected(($filtros['tiporetencion'] ?? '') === 'S')>SUSS</option>
                <option value="B" @selected(($filtros['tiporetencion'] ?? '') === 'B')>IIBB</option>
            </select>
        </div>
        <div class="col-md-2">
            <label>Nº OP</label>
            <input type="text" name="numero" class="form-control form-control-sm"
                   value="{{ $filtros['numero'] ?? '' }}" placeholder="Número OP">
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
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm mr-1">
                <i class="fa fa-search"></i> Consultar
            </button>
            <a href="{{ route('portal_proveedores_retenciones', ['proveedor_id' => $proveedorId]) }}"
               class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                <i class="fa fa-times"></i>
            </a>
        </div>
    </div>
</form>
