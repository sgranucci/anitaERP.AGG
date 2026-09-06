@php
    use App\Support\Compras\Tracking\TrackingFacturasListadoFiltros;

    $f = $filtros ?? [];
    $modo = $f['modo'] ?? TrackingFacturasListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'nombreproveedor';
    $operadorActivo = $f['operador'] ?? 'contiene';

    $operadoresJson = [];
    foreach (TrackingFacturasListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = TrackingFacturasListadoFiltros::operadoresParaCampo($key);
    }

    $tieneCriteriosPanel = TrackingFacturasListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('tracking_facturas', TrackingFacturasListadoFiltros::paraQueryStringExternos($f));
    $mostrarHasta = $operadorActivo === 'entre' && $modo === TrackingFacturasListadoFiltros::MODO_CAMPO;
@endphp
<div class="collapse border-bottom" id="panel-filtros-tracking-facturas" data-listado-filtros-panel>
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
                    <option value="{{ TrackingFacturasListadoFiltros::MODO_TODOS }}"
                            {{ $modo === TrackingFacturasListadoFiltros::MODO_TODOS ? 'selected' : '' }}>
                        Cualquier campo
                    </option>
                    <option value="{{ TrackingFacturasListadoFiltros::MODO_CAMPO }}"
                            {{ $modo === TrackingFacturasListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>
                        Campo determinado
                    </option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap"
                 style="{{ $modo !== TrackingFacturasListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach ($camposFiltro ?? TrackingFacturasListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}"
                                {{ $campoActivo === $key ? 'selected' : '' }}>
                            {{ $meta['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condici&oacute;n</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach (TrackingFacturasListadoFiltros::operadoresParaCampo($modo === TrackingFacturasListadoFiltros::MODO_CAMPO ? $campoActivo : 'nombreproveedor') as $opKey => $opLabel)
                        <option value="{{ $opKey }}" {{ $operadorActivo === $opKey ? 'selected' : '' }}>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text" id="filtro_valor_panel" class="form-control form-control-sm"
                       value="{{ $f['valor'] ?? '' }}"
                       placeholder="Texto, n&uacute;mero o fecha (dd/mm/aaaa)"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-valor-hasta-wrap"
                 style="{{ $mostrarHasta ? '' : 'display:none' }}">
                <label class="small mb-1" for="filtro_valor_hasta">Hasta</label>
                <input type="text" name="filtro_valor_hasta" id="filtro_valor_hasta"
                       class="form-control form-control-sm"
                       value="{{ $f['valor_hasta'] ?? '' }}"
                       placeholder="Fecha hasta"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
