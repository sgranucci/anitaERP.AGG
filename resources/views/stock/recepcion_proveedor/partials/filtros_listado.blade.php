@php
    use App\Support\Stock\RecepcionProveedorListadoFiltros;
    $f = $filtros ?? [];
    $campoActivo = $f['filtro_campo'] ?? '';
    $operadorActivo = $f['filtro_operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (RecepcionProveedorListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = RecepcionProveedorListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = RecepcionProveedorListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('recepcion_proveedor');
@endphp
<div class="collapse border-bottom" id="panel-filtros-recepcion" data-listado-filtros-panel>
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
            <div class="form-group col-md-3 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    <option value="">—</option>
                    @foreach($camposFiltro ?? RecepcionProveedorListadoFiltros::CAMPOS as $clave => $meta)
                        <option value="{{ $clave }}" data-type="{{ $meta['tipo'] }}" @selected($campoActivo === $clave)>{{ $meta['etiqueta'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condici&oacute;n</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(RecepcionProveedorListadoFiltros::operadoresParaCampo($campoActivo !== '' ? $campoActivo : 'numerorecepcion') as $opKey => $opLabel)
                        <option value="{{ $opKey }}" @selected($operadorActivo === $opKey)>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-4 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text"
                       id="filtro_valor_panel"
                       class="form-control form-control-sm"
                       value="{{ $f['filtro_valor'] ?? '' }}"
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
