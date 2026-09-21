@php
    use App\Support\Caja\ChequeListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? ChequeListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'numerocheque';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (ChequeListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = ChequeListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = ChequeListadoFiltros::tieneCriteriosTexto($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('cheque', ChequeListadoFiltros::paraQueryStringExternos($f));
    $fScope = $f['empresa_scope'] ?? 'una';
    $fEmp = (int) ($f['empresa_id'] ?? 0);
    $fCartera = ! empty($f['cartera']);
    $fOrigen = (string) ($f['origen'] ?? '');
    $fEstado = (string) ($f['estado'] ?? '');
@endphp
<div class="collapse border-bottom" id="panel-filtros-cheque" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    {{-- Persistencia de filtros externos al buscar por texto o aplicar el panel --}}
    @if ($fScope === 'todas')
        <input type="hidden" name="empresa_todas" value="1">
    @elseif ($fEmp > 0)
        <input type="hidden" name="empresa_id" value="{{ $fEmp }}">
    @endif
    @if ($fCartera)
        <input type="hidden" name="cartera" value="1">
    @endif
    @if ($fOrigen !== '')
        <input type="hidden" name="origen" value="{{ $fOrigen }}">
    @endif
    @if ($fEstado !== '')
        <input type="hidden" name="estado" value="{{ $fEstado }}">
    @endif
    @if (($f['orden'] ?? 'fechapago') !== 'fechapago' || ($f['orden_dir'] ?? 'desc') !== 'desc')
        <input type="hidden" name="orden" value="{{ $f['orden'] ?? 'fechapago' }}">
        <input type="hidden" name="orden_dir" value="{{ $f['orden_dir'] ?? 'desc' }}">
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
                    <option value="{{ ChequeListadoFiltros::MODO_TODOS }}" {{ $modo === ChequeListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ ChequeListadoFiltros::MODO_CAMPO }}" {{ $modo === ChequeListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== ChequeListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? ChequeListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(ChequeListadoFiltros::operadoresParaCampo($modo === ChequeListadoFiltros::MODO_CAMPO ? $campoActivo : 'numerocheque') as $opKey => $opLabel)
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
