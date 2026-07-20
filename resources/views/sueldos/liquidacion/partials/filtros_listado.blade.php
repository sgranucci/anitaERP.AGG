@php
    use App\Support\Sueldos\LiquidacionSueldosListadoFiltros;
    use App\Models\Sueldos\Liquidacion_Sueldos;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? LiquidacionSueldosListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'descripcion';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (LiquidacionSueldosListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = LiquidacionSueldosListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = LiquidacionSueldosListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('consultar_liquidacion_sueldos');
@endphp
<div class="collapse border-bottom" id="panel-filtros-liquidacion-sueldos" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
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
                <label class="small mb-1" for="filtro_empresa_id">Empresa</label>
                <select name="filtro_empresa_id" id="filtro_empresa_id" class="form-control form-control-sm">
                    <option value="">Todas</option>
                    @foreach(($empresas ?? []) as $emp)
                        <option value="{{ $emp->id }}" {{ (int)($f['empresa_id'] ?? 0) === (int)$emp->id ? 'selected' : '' }}>{{ $emp->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_tipo">Tipo</label>
                <select name="filtro_tipo" id="filtro_tipo" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach(Liquidacion_Sueldos::TIPOS as $k => $v)
                        <option value="{{ $k }}" {{ ($f['tipo'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_estado">Estado</label>
                <select name="filtro_estado" id="filtro_estado" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach(Liquidacion_Sueldos::ESTADOS as $k => $v)
                        <option value="{{ $k }}" {{ ($f['estado'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ LiquidacionSueldosListadoFiltros::MODO_TODOS }}" {{ $modo === LiquidacionSueldosListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ LiquidacionSueldosListadoFiltros::MODO_CAMPO }}" {{ $modo === LiquidacionSueldosListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== LiquidacionSueldosListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? LiquidacionSueldosListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(LiquidacionSueldosListadoFiltros::operadoresParaCampo($modo === LiquidacionSueldosListadoFiltros::MODO_CAMPO ? $campoActivo : 'descripcion') as $opKey => $opLabel)
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
