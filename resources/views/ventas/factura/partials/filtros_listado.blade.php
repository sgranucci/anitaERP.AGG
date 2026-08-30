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
    $fScope = $f['empresa_scope'] ?? 'una';
    $fEmp = (int) ($f['empresa_id'] ?? 0);
@endphp
<div class="px-3 py-2 border-bottom bg-light text-body">
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <input type="hidden" name="filtro_orden" id="filtro_orden" value="{{ FacturaListadoFiltros::normalizarOrden($f['orden'] ?? null) }}">
    @if ($fScope === 'todas')
        <input type="hidden" name="empresa_todas" value="1">
    @elseif ($fEmp > 0)
        <input type="hidden" name="empresa_id" value="{{ $fEmp }}">
    @endif
    <div class="form-row align-items-end">
        <div class="form-group col-auto mb-0 mr-2">
            <label class="small mb-1" for="fecha_desde">Fecha desde</label>
            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                   value="{{ $f['fecha_desde'] ?? date('Y-m-d') }}">
        </div>
        <div class="form-group col-auto mb-0 mr-2">
            <label class="small mb-1" for="fecha_hasta">Fecha hasta</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                   value="{{ $f['fecha_hasta'] ?? date('Y-m-d') }}">
        </div>
        <div class="form-group col-auto mb-0">
            <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1" title="Aplicar el rango de fechas">
                <i class="fa fa-calendar-check"></i> Aplicar fechas
            </button>
        </div>
    </div>
</div>
<div class="collapse border-bottom" id="panel-filtros-factura" data-listado-filtros-panel>
    <div class="card-body bg-light py-2 text-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_reparto">N&ordm; reparto</label>
                <input type="text"
                       name="filtro_reparto"
                       id="filtro_reparto"
                       class="form-control form-control-sm"
                       value="{{ $f['filtro_reparto'] ?? '' }}"
                       placeholder="Ej: 101 &oacute; 10/20"
                       autocomplete="off"
                       title="N&uacute;mero de reparto. Coma = lista (1,3,5); barra / = rango (10/20). Vac&iacute;o = todos.">
            </div>
            <div class="form-group col-md-auto mb-2">
                <div class="custom-control custom-checkbox mt-4">
                    <input type="checkbox"
                           class="custom-control-input"
                           name="solo_sin_remito"
                           id="solo_sin_remito"
                           value="1"
                           {{ !empty($f['solo_sin_remito']) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="solo_sin_remito">
                        Sin remito
                    </label>
                </div>
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
