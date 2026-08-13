@php
    use App\Support\Ventas\PedidoListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? PedidoListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'codigo';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (PedidoListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = PedidoListadoFiltros::operadoresParaCampo($key);
    }
@endphp
@include('includes.listado.filtros_estilos_activos')
<div class="collapse border-bottom" id="panel-filtros-pedido" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-light py-2 text-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2 mb-md-0">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ PedidoListadoFiltros::MODO_TODOS }}" {{ $modo === PedidoListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ PedidoListadoFiltros::MODO_CAMPO }}" {{ $modo === PedidoListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 mb-md-0 filtro-campo-wrap" style="{{ $modo !== PedidoListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? PedidoListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 mb-md-0">
                <label class="small mb-1" for="filtro_operador">Condici&oacute;n</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(PedidoListadoFiltros::operadoresParaCampo($modo === PedidoListadoFiltros::MODO_CAMPO ? $campoActivo : 'codigo') as $opKey => $opLabel)
                        <option value="{{ $opKey }}" {{ $operadorActivo === $opKey ? 'selected' : '' }}>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 mb-md-0">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text"
                       id="filtro_valor_panel"
                       class="form-control form-control-sm"
                       value="{{ $f['valor'] ?? '' }}"
                       placeholder="Texto, n&uacute;mero o fecha"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-2 col-sm-4 mb-2 mb-md-0 filtro-valor-hasta-wrap" style="display:none">
                <label class="small mb-1" for="filtro_valor_hasta">Hasta</label>
                <input type="text" name="filtro_valor_hasta" id="filtro_valor_hasta" class="form-control form-control-sm"
                       value="{{ $f['valor_hasta'] ?? '' }}"
                       placeholder="dd/mm/aaaa">
            </div>
            <div class="form-group col-md-auto mb-2 mb-md-0">
                <label class="small mb-1 d-block listado-filtros-label-spacer" aria-hidden="true">&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
