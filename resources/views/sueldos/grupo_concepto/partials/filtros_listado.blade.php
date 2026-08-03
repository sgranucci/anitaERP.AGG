@php
    use App\Support\Sueldos\GrupoConceptoSueldosListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? GrupoConceptoSueldosListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'descripcion';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (GrupoConceptoSueldosListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = GrupoConceptoSueldosListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = GrupoConceptoSueldosListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('consultar_grupo_concepto_sueldos', GrupoConceptoSueldosListadoFiltros::paraQueryStringEmpresa($f));
@endphp
<div class="collapse border-bottom" id="panel-filtros-grupo-concepto-sueldos" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    @if (($filtros['empresa_scope'] ?? 'una') === 'todas')
        <input type="hidden" name="empresa_todas" value="1">
    @elseif (! empty($filtros['empresa_id']))
        <input type="hidden" name="empresa_id" value="{{ (int) $filtros['empresa_id'] }}">
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
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ GrupoConceptoSueldosListadoFiltros::MODO_TODOS }}" {{ $modo === GrupoConceptoSueldosListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ GrupoConceptoSueldosListadoFiltros::MODO_CAMPO }}" {{ $modo === GrupoConceptoSueldosListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== GrupoConceptoSueldosListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? GrupoConceptoSueldosListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(GrupoConceptoSueldosListadoFiltros::operadoresParaCampo($modo === GrupoConceptoSueldosListadoFiltros::MODO_CAMPO ? $campoActivo : 'descripcion') as $opKey => $opLabel)
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
