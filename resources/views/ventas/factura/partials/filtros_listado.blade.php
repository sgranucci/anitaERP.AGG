@php
    use App\Support\Ventas\FacturaListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? FacturaListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'cliente';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (FacturaListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = FacturaListadoFiltros::operadoresParaCampo($key);
    }
@endphp
<div class="collapse border-bottom" id="panel-filtros-factura" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-light py-2 text-body">
        <div class="form-row align-items-end">
            @include('includes.listado.filtro_empresa_asignada', [
                'empresa_query' => $empresa_query ?? collect(),
                'empresa_id' => $f['empresa_id'] ?? 0,
                'col_class' => 'col-md-3 col-sm-6 mb-2',
            ])
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_desde">Fecha desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                       value="{{ $f['fecha_desde'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_hasta">Fecha hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                       value="{{ $f['fecha_hasta'] ?? '' }}">
            </div>
        </div>
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ FacturaListadoFiltros::MODO_TODOS }}" {{ $modo === FacturaListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ FacturaListadoFiltros::MODO_CAMPO }}" {{ $modo === FacturaListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== FacturaListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? FacturaListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{!! $meta['label'] !!}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(FacturaListadoFiltros::operadoresParaCampo($modo === FacturaListadoFiltros::MODO_CAMPO ? $campoActivo : 'cliente') as $opKey => $opLabel)
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
                       placeholder="Texto o número"
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
