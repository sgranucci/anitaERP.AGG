@php
    use App\Support\Stock\MovimientoStockListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? MovimientoStockListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'codigo';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (MovimientoStockListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = MovimientoStockListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = MovimientoStockListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('movimientostock');
    $empresaIdSeleccion = (int) ($f['empresa_id'] ?? 0);
    $depositoIdSeleccion = (int) ($f['deposito_id'] ?? 0);
    $empresasDisponibles = collect($empresa_query ?? []);
    $depositosDisponibles = collect($deposito_query ?? []);
@endphp
<div class="collapse border-bottom" id="panel-filtros-movimientostock" data-listado-filtros-panel>
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
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ MovimientoStockListadoFiltros::MODO_TODOS }}" {{ $modo === MovimientoStockListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ MovimientoStockListadoFiltros::MODO_CAMPO }}" {{ $modo === MovimientoStockListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== MovimientoStockListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? MovimientoStockListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condici&oacute;n</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(MovimientoStockListadoFiltros::operadoresParaCampo($modo === MovimientoStockListadoFiltros::MODO_CAMPO ? $campoActivo : 'codigo') as $opKey => $opLabel)
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
        <hr class="my-2">
        <div class="form-row align-items-end">
            @if ($empresasDisponibles->count() > 1)
                <div class="form-group col-md-3 col-sm-6 mb-2">
                    <label class="small mb-1" for="empresa_id">Empresa</label>
                    <select name="empresa_id" id="empresa_id" class="form-control form-control-sm">
                        <option value="">Todas (asignadas)</option>
                        @foreach ($empresasDisponibles as $emp)
                            <option value="{{ $emp->id }}" @selected($empresaIdSeleccion === (int) $emp->id)>{{ $emp->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif ($empresasDisponibles->count() === 1)
                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}"/>
            @endif
            @if (($mostrarFiltroDeposito ?? false) && $depositosDisponibles->count() > 1)
                <div class="form-group col-md-3 col-sm-6 mb-2">
                    <label class="small mb-1" for="deposito_id">Dep&oacute;sito</label>
                    <select name="deposito_id" id="deposito_id" class="form-control form-control-sm">
                        <option value="">Todos (autorizados)</option>
                        @foreach ($depositosDisponibles as $dep)
                            <option value="{{ $dep->id }}" @selected($depositoIdSeleccion === (int) $dep->id)>{{ $dep->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif ($depositosDisponibles->count() === 1)
                <input type="hidden" name="deposito_id" id="deposito_id" value="{{ (int) $depositosDisponibles->first()->id }}"/>
            @endif
        </div>
    </div>
</div>
