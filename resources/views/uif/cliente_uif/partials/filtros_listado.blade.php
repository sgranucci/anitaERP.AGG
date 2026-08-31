@php
    use App\Support\Uif\ClienteUifListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? ClienteUifListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'nombre';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (ClienteUifListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = ClienteUifListadoFiltros::operadoresParaCampo($key);
    }
@endphp
@php
    $tieneCriteriosPanel = ClienteUifListadoFiltros::tieneCriteriosTexto($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('consulta_cliente_uif', ClienteUifListadoFiltros::paraQueryStringEmpresa($f));
    $fScope = $f['empresa_scope'] ?? 'todas';
    $fEmp = (int) ($f['empresa_id'] ?? 0);
@endphp
<div class="collapse border-bottom" id="panel-filtros-cliente-uif" data-listado-filtros-panel>
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
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ ClienteUifListadoFiltros::MODO_TODOS }}" {{ $modo === ClienteUifListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ ClienteUifListadoFiltros::MODO_CAMPO }}" {{ $modo === ClienteUifListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== ClienteUifListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? ClienteUifListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(ClienteUifListadoFiltros::operadoresParaCampo($modo === ClienteUifListadoFiltros::MODO_CAMPO ? $campoActivo : 'nombre') as $opKey => $opLabel)
                        <option value="{{ $opKey }}" {{ $operadorActivo === $opKey ? 'selected' : '' }}>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text"
                       id="filtro_valor_panel"
                       class="form-control form-control-sm"
                       value="{{ $f['valor'] ?? '' }}"
                       placeholder="Texto, número o fecha"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-2 col-sm-4 mb-2 filtro-valor-hasta-wrap" style="display:none">
                <label class="small mb-1" for="filtro_valor_hasta">Hasta</label>
                <input type="text" name="filtro_valor_hasta" id="filtro_valor_hasta" class="form-control form-control-sm"
                       value="{{ $f['valor_hasta'] ?? '' }}"
                       placeholder="dd/mm/aaaa">
            </div>
            @php
                $uifCtxFiltro = \App\Support\Uif\ClienteUifOrigenPcSupport::contexto();
                $origenesFiltro = \App\Support\Uif\ClienteUifOrigenPcSupport::opcionesOrigen(
                    $uifCtxFiltro['origenes_permitidos'] ?: null
                );
                // Con empresa externa elegida el origen ya está fijado; el select solo aplica en "Todas".
                $mostrarOrigenPanel = ($fScope === 'todas');
                $origenSeleccionado = (string) ($f['anita_origen'] ?? '');
            @endphp
            @if ($mostrarOrigenPanel)
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_anita_origen">Origen / sala</label>
                <select name="filtro_anita_origen" id="filtro_anita_origen" class="form-control form-control-sm">
                    <option value="todos" {{ $origenSeleccionado === '' ? 'selected' : '' }}>Todos</option>
                    @foreach($origenesFiltro as $origenKey => $origenLabel)
                        <option value="{{ $origenKey }}" {{ $origenSeleccionado === $origenKey ? 'selected' : '' }}>{{ $origenLabel }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_estado">Estado</label>
                <select name="filtro_estado" id="filtro_estado" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Uif\Cliente_Uif::$enumEstado as $est)
                        <option value="{{ $est['valor'] }}" {{ ($f['estado'] ?? '') === $est['valor'] ? 'selected' : '' }}>{{ $est['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_premios">Premios</label>
                @php
                    $premiosFiltro = ! empty($f['sin_premios']) ? 'sin' : (! empty($f['con_premios']) ? 'con' : '');
                @endphp
                <select name="filtro_premios" id="filtro_premios" class="form-control form-control-sm">
                    <option value="" {{ $premiosFiltro === '' ? 'selected' : '' }}>Todos</option>
                    <option value="con" {{ $premiosFiltro === 'con' ? 'selected' : '' }}>Con premio</option>
                    <option value="sin" {{ $premiosFiltro === 'sin' ? 'selected' : '' }}>Sin premio</option>
                </select>
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
