@php
    use App\Support\Uif\ClientePremioUifListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? ClientePremioUifListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'nombre';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (ClientePremioUifListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = ClientePremioUifListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = ClientePremioUifListadoFiltros::tieneCriteriosTexto($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('consulta_cliente_premio_uif', ClientePremioUifListadoFiltros::paraQueryStringEmpresa($f));
    $fScope = $f['empresa_scope'] ?? 'todas';
    $fEmp = (int) ($f['empresa_id'] ?? 0);
    $origenSeleccionado = (string) ($f['anita_origen'] ?? '');
@endphp
<div class="collapse border-bottom" id="panel-filtros-cliente-premio-uif" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
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
                    <option value="{{ ClientePremioUifListadoFiltros::MODO_TODOS }}" {{ $modo === ClientePremioUifListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ ClientePremioUifListadoFiltros::MODO_CAMPO }}" {{ $modo === ClientePremioUifListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== ClientePremioUifListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? ClientePremioUifListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(ClientePremioUifListadoFiltros::operadoresParaCampo($modo === ClientePremioUifListadoFiltros::MODO_CAMPO ? $campoActivo : 'nombre') as $opKey => $opLabel)
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
                       placeholder="Texto o número"
                       autocomplete="off">
            </div>
            @php
                $uifCtxFiltro = \App\Support\Uif\ClienteUifOrigenPcSupport::contexto();
                $origenesFiltro = \App\Support\Uif\ClienteUifOrigenPcSupport::opcionesOrigen(
                    $uifCtxFiltro['origenes_permitidos'] ?: null
                );
            @endphp
            @if ($fScope === 'todas')
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
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
