@php
    use App\Support\Ventas\GastronomiaArticulosVendidosListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'descripcion';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (GastronomiaArticulosVendidosListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = GastronomiaArticulosVendidosListadoFiltros::operadoresParaCampo($key);
    }
@endphp
<div class="collapse border-bottom" id="panel-filtros-articulos-vendidos" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-white py-2 text-body">
        <p class="small text-muted mb-2 mb-md-1">Refinar el listado (opcional). El reporte siempre incluye todas las terminales de la empresa.</p>
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="deposito_id">Depósito</label>
                <select name="deposito_id" id="deposito_id" class="form-control form-control-sm">
                    <option value="">Todos los depósitos</option>
                    @foreach ($deposito_query ?? [] as $dep)
                        <option value="{{ $dep->id }}" @selected((int) ($f['deposito_id'] ?? 0) === (int) $dep->id)>
                            {{ trim($dep->codigo.' '.$dep->nombre) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS }}" {{ $modo === GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO }}" {{ $modo === GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? GastronomiaArticulosVendidosListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(GastronomiaArticulosVendidosListadoFiltros::operadoresParaCampo($campoActivo) as $opKey => $opLabel)
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
                       placeholder="SKU, descripción, depósito…"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-outline-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-filter"></i> Aplicar
                </button>
            </div>
        </div>
    </div>
</div>
