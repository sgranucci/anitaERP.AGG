@php
    use App\Support\Contable\CierreRendicionEstacionamientoListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? CierreRendicionEstacionamientoListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'codigo';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (CierreRendicionEstacionamientoListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = CierreRendicionEstacionamientoListadoFiltros::operadoresParaCampo($key);
    }
    $estadoCierre = $f['estado_cierre'] ?? CierreRendicionEstacionamientoListadoFiltros::ESTADO_TODOS;
    $fScope = $f['empresa_scope'] ?? 'una';
    $fEmp = (int) ($f['empresa_id'] ?? 0);
@endphp
<div class="collapse border-bottom" id="panel-filtros-cierre-rend-est" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    @if ($fScope === 'todas')
        <input type="hidden" name="empresa_todas" value="1">
    @elseif ($fEmp > 0)
        <input type="hidden" name="empresa_id" value="{{ $fEmp }}">
    @endif
    <div class="card-body bg-light py-2 text-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="vista">Vista del listado</label>
                <select name="vista" id="vista" class="form-control form-control-sm">
                    <option value="{{ CierreRendicionEstacionamientoListadoFiltros::VISTA_AGRUPADO }}" @selected(($f['vista'] ?? CierreRendicionEstacionamientoListadoFiltros::VISTA_AGRUPADO) === CierreRendicionEstacionamientoListadoFiltros::VISTA_AGRUPADO)>Unificado (PV + fecha)</option>
                    <option value="{{ CierreRendicionEstacionamientoListadoFiltros::VISTA_POR_TURNO }}" @selected(($f['vista'] ?? '') === CierreRendicionEstacionamientoListadoFiltros::VISTA_POR_TURNO)>Por turno</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="estado_cierre">Estado cierre contable</label>
                <select name="estado_cierre" id="estado_cierre" class="form-control form-control-sm">
                    <option value="{{ CierreRendicionEstacionamientoListadoFiltros::ESTADO_TODOS }}" @selected($estadoCierre === CierreRendicionEstacionamientoListadoFiltros::ESTADO_TODOS)>Todos</option>
                    <option value="{{ CierreRendicionEstacionamientoListadoFiltros::ESTADO_PENDIENTE }}" @selected($estadoCierre === CierreRendicionEstacionamientoListadoFiltros::ESTADO_PENDIENTE)>Pendiente</option>
                    <option value="{{ CierreRendicionEstacionamientoListadoFiltros::ESTADO_CERRADA }}" @selected($estadoCierre === CierreRendicionEstacionamientoListadoFiltros::ESTADO_CERRADA)>Cerrada</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_jornada_desde">Desde (fecha jornada)</label>
                <input type="date" name="fecha_jornada_desde" id="fecha_jornada_desde" class="form-control form-control-sm" value="{{ $f['fecha_jornada_desde'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_jornada_hasta">Hasta (fecha jornada)</label>
                <input type="date" name="fecha_jornada_hasta" id="fecha_jornada_hasta" class="form-control form-control-sm" value="{{ $f['fecha_jornada_hasta'] ?? '' }}" data-fecha-jornada-hasta>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ CierreRendicionEstacionamientoListadoFiltros::MODO_TODOS }}" @selected($modo === CierreRendicionEstacionamientoListadoFiltros::MODO_TODOS)>Cualquier campo</option>
                    <option value="{{ CierreRendicionEstacionamientoListadoFiltros::MODO_CAMPO }}" @selected($modo === CierreRendicionEstacionamientoListadoFiltros::MODO_CAMPO)>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== CierreRendicionEstacionamientoListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? CierreRendicionEstacionamientoListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" @selected($campoActivo === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condici&oacute;n</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm" data-operadores='@json($operadoresJson)'>
                    @foreach(CierreRendicionEstacionamientoListadoFiltros::operadoresParaCampo($modo === CierreRendicionEstacionamientoListadoFiltros::MODO_CAMPO ? $campoActivo : 'codigo') as $opKey => $opLabel)
                        <option value="{{ $opKey }}" @selected($operadorActivo === $opKey)>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-valor-hasta-wrap" style="display:none;">
                <label class="small mb-1" for="filtro_valor_hasta">Hasta (entre fechas)</label>
                <input type="text" name="filtro_valor_hasta" id="filtro_valor_hasta" class="form-control form-control-sm" value="{{ $f['valor_hasta'] ?? '' }}" placeholder="AAAA-MM-DD" autocomplete="off">
            </div>
            <div class="form-group col-md-3 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text" id="filtro_valor_panel" class="form-control form-control-sm" value="{{ $f['valor'] ?? '' }}" placeholder="Texto, ID, ticket…" autocomplete="off">
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
