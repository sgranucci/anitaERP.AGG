@php
    use App\Support\Contable\CuentacontableArbolSupport;
    use App\Support\Contable\CuentacontableListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? CuentacontableListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'nombre';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (CuentacontableListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = CuentacontableListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = CuentacontableListadoFiltros::tieneCriteriosTexto($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('cuentacontable', CuentacontableListadoFiltros::paraQueryStringEmpresa($f));
    $fScope = $f['empresa_scope'] ?? 'una';
    $fEmp = (int) ($f['empresa_id'] ?? 0);
    $vista = $f['vista'] ?? CuentacontableListadoFiltros::VISTA_ARBOL;
@endphp
<div class="collapse border-bottom" id="panel-filtros-cuentacontable" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <input type="hidden" name="vista" value="{{ $vista }}">
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
                    <option value="{{ CuentacontableListadoFiltros::MODO_TODOS }}" {{ $modo === CuentacontableListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ CuentacontableListadoFiltros::MODO_CAMPO }}" {{ $modo === CuentacontableListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== CuentacontableListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? CuentacontableListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(CuentacontableListadoFiltros::operadoresParaCampo($modo === CuentacontableListadoFiltros::MODO_CAMPO ? $campoActivo : 'nombre') as $opKey => $opLabel)
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
                       placeholder="Código o nombre"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_tipocuenta">Tipo</label>
                <select name="filtro_tipocuenta" id="filtro_tipocuenta" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach($tiposCuenta ?? CuentacontableArbolSupport::etiquetasTipo() as $valor => $label)
                        <option value="{{ $valor }}" @selected(($f['tipocuenta'] ?? '') === (string) $valor)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-1 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_nivel">Nivel</label>
                <select name="filtro_nivel" id="filtro_nivel" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @for ($n = 1; $n <= 5; $n++)
                        <option value="{{ $n }}" @selected((int) ($f['nivel'] ?? 0) === $n)>{{ $n }}</option>
                    @endfor
                </select>
            </div>
            <div class="form-group col-md-auto mb-2">
                <div class="custom-control custom-checkbox mt-3">
                    <input type="checkbox"
                           class="custom-control-input"
                           name="mostrar_totalizadoras"
                           id="mostrar_totalizadoras"
                           value="1"
                           @checked(!empty($f['mostrar_totalizadoras']))>
                    <label class="custom-control-label small" for="mostrar_totalizadoras">Mostrar totalizadoras</label>
                </div>
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
