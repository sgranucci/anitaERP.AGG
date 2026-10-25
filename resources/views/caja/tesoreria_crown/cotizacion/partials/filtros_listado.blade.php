@php
    use App\Support\Caja\TesoreriaCrownCotizacionListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? TesoreriaCrownCotizacionListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'jornada';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (TesoreriaCrownCotizacionListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = TesoreriaCrownCotizacionListadoFiltros::operadoresParaCampo($key);
    }
@endphp
<div class="collapse border-bottom" id="panel-filtros-tesoreria-crown-cotizacion" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <input type="hidden" name="sala_id" value="{{ (int) ($sala?->id ?? 0) }}">
    <div class="card-body bg-light py-2 text-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ TesoreriaCrownCotizacionListadoFiltros::MODO_TODOS }}" @selected($modo === TesoreriaCrownCotizacionListadoFiltros::MODO_TODOS)>Cualquier campo</option>
                    <option value="{{ TesoreriaCrownCotizacionListadoFiltros::MODO_CAMPO }}" @selected($modo === TesoreriaCrownCotizacionListadoFiltros::MODO_CAMPO)>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== TesoreriaCrownCotizacionListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach (TesoreriaCrownCotizacionListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" @selected($campoActivo === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm" data-operadores='@json($operadoresJson)'>
                    @foreach (TesoreriaCrownCotizacionListadoFiltros::operadoresParaCampo($modo === TesoreriaCrownCotizacionListadoFiltros::MODO_CAMPO ? $campoActivo : 'jornada') as $opKey => $opLabel)
                        <option value="{{ $opKey }}" @selected($operadorActivo === $opKey)>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text" id="filtro_valor_panel" class="form-control form-control-sm" value="{{ $f['valor'] ?? '' }}" placeholder="Fecha d/m/Y o año" autocomplete="off">
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
