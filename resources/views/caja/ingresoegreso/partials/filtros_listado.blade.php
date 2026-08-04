@php
    use App\Support\Caja\IngresoEgresoListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? IngresoEgresoListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'detalle';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $camposVista = $camposFiltro ?? IngresoEgresoListadoFiltros::camposParaVista();
    $operadoresJson = [];
    foreach ($camposVista as $key => $meta) {
        $operadoresJson[$key] = IngresoEgresoListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = IngresoEgresoListadoFiltros::tieneCriteriosTexto($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('ingresoegreso', IngresoEgresoListadoFiltros::paraQueryStringEmpresa($f));
    $fScope = $f['empresa_scope'] ?? 'una';
    $fEmp = (int) ($f['empresa_id'] ?? 0);
@endphp
<div class="collapse border-bottom" id="panel-filtros-ingresoegreso" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    @if ($fScope === 'todas')
        <input type="hidden" name="empresa_todas" value="1">
    @elseif ($fEmp > 0)
        <input type="hidden" name="empresa_id" value="{{ $fEmp }}">
    @endif
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
                <label class="small mb-1" for="fecha_desde">Desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                       value="{{ $f['fecha_desde'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_hasta">Hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                       value="{{ $f['fecha_hasta'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ IngresoEgresoListadoFiltros::MODO_TODOS }}" {{ $modo === IngresoEgresoListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ IngresoEgresoListadoFiltros::MODO_CAMPO }}" {{ $modo === IngresoEgresoListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== IngresoEgresoListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach ($camposVista as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach (IngresoEgresoListadoFiltros::operadoresParaCampo($modo === IngresoEgresoListadoFiltros::MODO_CAMPO ? $campoActivo : 'detalle') as $opKey => $opLabel)
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
                       placeholder="Texto (tolera errores de tipeo desde 6 caracteres)"
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
