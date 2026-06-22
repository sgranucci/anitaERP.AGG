@php
    use App\Support\Contable\BienUsoListadoFiltros;
    use App\Models\Contable\BienUso;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? BienUsoListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'hostname';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (BienUsoListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = BienUsoListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = BienUsoListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('bien_uso');
@endphp
<div class="collapse border-bottom" id="panel-filtros-bien-uso" data-listado-filtros-panel>
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
                <label class="small mb-1" for="filtro_centrocosto_id">Centro de costo</label>
                @if(!empty($filtro_centrocosto_restringido))
                    <input type="hidden" name="filtro_centrocosto_id" value="{{ $f['centrocosto_id'] ?? '' }}">
                    <input type="text" id="filtro_centrocosto_id" class="form-control form-control-sm" readonly
                           value="{{ $alcance_centro_costo ?? '' }}">
                @else
                    <select name="filtro_centrocosto_id" id="filtro_centrocosto_id" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach($centrocosto_opciones ?? [] as $cc)
                            <option value="{{ $cc->id }}" {{ (int) ($f['centrocosto_id'] ?? 0) === (int) $cc->id ? 'selected' : '' }}>
                                {{ $cc->codigo }} — {{ $cc->nombre }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ BienUsoListadoFiltros::MODO_TODOS }}" {{ $modo === BienUsoListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ BienUsoListadoFiltros::MODO_CAMPO }}" {{ $modo === BienUsoListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== BienUsoListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? BienUsoListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(BienUsoListadoFiltros::operadoresParaCampo($modo === BienUsoListadoFiltros::MODO_CAMPO ? $campoActivo : 'hostname') as $opKey => $opLabel)
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
