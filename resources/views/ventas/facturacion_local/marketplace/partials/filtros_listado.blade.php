@php
    use App\Support\Ventas\FacturacionLocal\MarketplaceListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? MarketplaceListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'nombre';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (MarketplaceListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = MarketplaceListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = MarketplaceListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('facturacion_local_marketplaces');
@endphp
<div class="collapse border-bottom" id="panel-filtros-marketplace" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-light py-2 text-body">
        @if ($tieneCriteriosPanel)
            <div class="mb-2">
                @include('includes.listado.filtros_aviso_activos', [
                    'tieneCriterios' => true,
                    'limpiarUrl' => $limpiarUrlPanel,
                    'compact' => true,
                ])
            </div>
        @endif
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ MarketplaceListadoFiltros::MODO_TODOS }}" {{ $modo === MarketplaceListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ MarketplaceListadoFiltros::MODO_CAMPO }}" {{ $modo === MarketplaceListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== MarketplaceListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach ($camposFiltro ?? MarketplaceListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach (MarketplaceListadoFiltros::operadoresParaCampo($modo === MarketplaceListadoFiltros::MODO_CAMPO ? $campoActivo : 'nombre') as $opKey => $opLabel)
                        <option value="{{ $opKey }}" {{ $operadorActivo === $opKey ? 'selected' : '' }}>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text"
                       id="filtro_valor_panel"
                       class="form-control form-control-sm"
                       value="{{ $f['valor'] ?? '' }}"
                       placeholder="Texto (tolera errores de tipeo)"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_activo">Estado</label>
                <select name="filtro_activo" id="filtro_activo" class="form-control form-control-sm">
                    <option value="" {{ ($f['activo'] ?? '') === '' ? 'selected' : '' }}>Todos</option>
                    <option value="1" {{ ($f['activo'] ?? '') === '1' ? 'selected' : '' }}>Activos</option>
                    <option value="0" {{ ($f['activo'] ?? '') === '0' ? 'selected' : '' }}>Inactivos</option>
                </select>
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
