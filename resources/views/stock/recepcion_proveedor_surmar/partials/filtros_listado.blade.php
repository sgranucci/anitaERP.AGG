@php
    use App\Support\Stock\RecepcionProveedorSurmarListadoFiltros;
@endphp
<div class="collapse {{ RecepcionProveedorSurmarListadoFiltros::tieneCriteriosAplicados($filtros ?? []) ? 'show' : '' }}" id="panel-filtros-recepcion-surmar">
    <div class="card-body border-top">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3 mb-2">
                <label class="control-label" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm" form="form-filtros-recepcion-surmar">
                    <option value="">(todos)</option>
                    @foreach ($camposFiltro ?? [] as $key => $meta)
                        <option value="{{ $key }}" {{ ($filtros['filtro_campo'] ?? '') === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 mb-2">
                <label class="control-label" for="filtro_valor_panel">Valor</label>
                <input type="text" name="filtro_valor" id="filtro_valor_panel" class="form-control form-control-sm" form="form-filtros-recepcion-surmar" value="{{ $filtros['filtro_valor'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="control-label" for="estado">Estado</label>
                <select name="estado" id="estado" class="form-control form-control-sm" form="form-filtros-recepcion-surmar">
                    <option value="">Todos</option>
                    <option value="BORRADOR" {{ ($filtros['estado'] ?? '') === 'BORRADOR' ? 'selected' : '' }}>Provisorio</option>
                    <option value="CONFIRMADA" {{ ($filtros['estado'] ?? '') === 'CONFIRMADA' ? 'selected' : '' }}>Confirmada</option>
                    <option value="ANULADA" {{ ($filtros['estado'] ?? '') === 'ANULADA' ? 'selected' : '' }}>Anulada</option>
                </select>
            </div>
            <div class="form-group col-md-2 mb-2">
                <button type="submit" class="btn btn-primary btn-sm btn-block">Aplicar filtros</button>
            </div>
        </div>
        @include('includes.listado.filtros_aviso_activos', [
            'tieneCriterios' => RecepcionProveedorSurmarListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
            'limpiarUrl' => route('recepcion_proveedor_surmar'),
        ])
    </div>
</div>
