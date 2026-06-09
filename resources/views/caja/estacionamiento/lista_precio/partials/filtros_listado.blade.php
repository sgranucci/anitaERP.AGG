@php
    use App\Support\Caja\Estacionamiento\ListaPrecioEstacionamientoListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? ListaPrecioEstacionamientoListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'empresa';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (ListaPrecioEstacionamientoListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = ListaPrecioEstacionamientoListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = ListaPrecioEstacionamientoListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route('estacionamiento_lista_precio');
    $fechaReferencia = $f['fecha_referencia'] ?? date('Y-m-d');
@endphp
<div class="collapse border-bottom" id="panel-filtros-estacionamiento-lista-precio" data-listado-filtros-panel>
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
            @include('includes.listado.filtro_empresa_asignada', ['f' => $f])
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="categoria_automovil_id">Categor&iacute;a</label>
                <select name="categoria_automovil_id" id="categoria_automovil_id" class="form-control form-control-sm">
                    <option value="">Todas</option>
                    @foreach ($categoria_query ?? [] as $categoria)
                        <option value="{{ $categoria->id }}" {{ (int) ($f['categoria_automovil_id'] ?? 0) === (int) $categoria->id ? 'selected' : '' }}>
                            {{ $categoria->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_referencia">Precios vigentes al</label>
                <input type="date"
                       name="fecha_referencia"
                       id="fecha_referencia"
                       class="form-control form-control-sm"
                       value="{{ $fechaReferencia }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ ListaPrecioEstacionamientoListadoFiltros::MODO_TODOS }}" {{ $modo === ListaPrecioEstacionamientoListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ ListaPrecioEstacionamientoListadoFiltros::MODO_CAMPO }}" {{ $modo === ListaPrecioEstacionamientoListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== ListaPrecioEstacionamientoListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? ListaPrecioEstacionamientoListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condici&oacute;n</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(ListaPrecioEstacionamientoListadoFiltros::operadoresParaCampo($modo === ListaPrecioEstacionamientoListadoFiltros::MODO_CAMPO ? $campoActivo : 'empresa') as $opKey => $opLabel)
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
