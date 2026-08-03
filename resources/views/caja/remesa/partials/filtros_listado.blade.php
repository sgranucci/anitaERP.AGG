@php
    use App\Support\Caja\Remesa\RemesaSupport;
    use App\Support\Caja\RemesaListadoFiltros;

    $f = $filtros ?? [];
    $modo = $f['modo'] ?? RemesaListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'numero';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (RemesaListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = RemesaListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = RemesaListadoFiltros::tieneCriteriosTexto($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('remesa', RemesaListadoFiltros::paraQueryStringEmpresa($f));
    $fScope = $f['empresa_scope'] ?? 'una';
    $fEmp = (int) ($f['empresa_id'] ?? 0);
@endphp
<div class="collapse border-bottom" id="panel-filtros-remesa" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    {{-- Persistencia del filtro externo de empresa al buscar por texto o aplicar el panel --}}
    @if ($fScope === 'todas')
        <input type="hidden" name="empresa_todas" value="1">
    @elseif ($fEmp > 0)
        <input type="hidden" name="empresa_id" value="{{ $fEmp }}">
    @endif
    <div class="card-body bg-light py-2 text-body">
        @if($tieneCriteriosPanel)
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
                <label class="small mb-1" for="tipo_filtro">Tipo</label>
                <select name="tipo" id="tipo_filtro" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach (RemesaSupport::enumTipo() as $opt)
                        <option value="{{ $opt['valor'] }}" {{ ($f['tipo'] ?? '') === $opt['valor'] ? 'selected' : '' }}>{{ $opt['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="estado_filtro">Estado</label>
                <select name="estado" id="estado_filtro" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach (RemesaSupport::enumEstado() as $opt)
                        <option value="{{ $opt['valor'] }}" {{ ($f['estado'] ?? '') === $opt['valor'] ? 'selected' : '' }}>{{ $opt['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ RemesaListadoFiltros::MODO_TODOS }}" {{ $modo === RemesaListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ RemesaListadoFiltros::MODO_CAMPO }}" {{ $modo === RemesaListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== RemesaListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? RemesaListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condici&oacute;n</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(RemesaListadoFiltros::operadoresParaCampo($modo === RemesaListadoFiltros::MODO_CAMPO ? $campoActivo : 'numero') as $opKey => $opLabel)
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
                       placeholder="Texto o n&uacute;mero"
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
