@php
    use App\Support\Ventas\GastronomiaArticulosVendidosListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'descripcion';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (GastronomiaArticulosVendidosListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = GastronomiaArticulosVendidosListadoFiltros::operadoresParaCampo($key);
    }
@endphp
<div class="collapse border-bottom" id="panel-filtros-articulos-vendidos" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-light py-2 text-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="empresa_id">Empresa</label>
                <select name="empresa_id" id="empresa_id" class="form-control form-control-sm">
                    <option value="">Todas</option>
                    @foreach ($empresa_query as $emp)
                        <option value="{{ $emp->id }}" @selected((int) ($f['empresa_id'] ?? 0) === (int) $emp->id)>{{ $emp->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="puntoventa_id">Punto de venta</label>
                <select name="puntoventa_id" id="puntoventa_id" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach ($puntoventa_query ?? [] as $pv)
                        <option value="{{ $pv->id }}" @selected((int) ($f['puntoventa_id'] ?? 0) === (int) $pv->id)>
                            {{ trim($pv->codigo.' '.$pv->nombre) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="deposito_id">Depósito</label>
                <select name="deposito_id" id="deposito_id" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach ($deposito_query ?? [] as $dep)
                        <option value="{{ $dep->id }}" @selected((int) ($f['deposito_id'] ?? 0) === (int) $dep->id)>
                            {{ trim($dep->codigo.' '.$dep->nombre) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="jornada_id">Jornada</label>
                <select name="jornada_id" id="jornada_id" class="form-control form-control-sm"
                        title="Si elige jornada, reemplaza el rango de fechas">
                    <option value="">Por rango de fechas</option>
                    @foreach ($jornadas ?? [] as $j)
                        <option value="{{ $j->id }}" @selected((int) ($f['jornada_id'] ?? 0) === (int) $j->id)>
                            #{{ $j->id }} — {{ $j->fecha_jornada?->format('d/m/Y') }}
                            @if ($j->estado === 'abierta')
                                (abierta)
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_desde">Jornada desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                       value="{{ $f['fecha_desde'] ?? '' }}" @disabled((int) ($f['jornada_id'] ?? 0) > 0)/>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_hasta">Jornada hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                       value="{{ $f['fecha_hasta'] ?? '' }}" data-fecha-jornada-hasta
                       @disabled((int) ($f['jornada_id'] ?? 0) > 0)/>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS }}" {{ $modo === GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO }}" {{ $modo === GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== GastronomiaArticulosVendidosListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? GastronomiaArticulosVendidosListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(GastronomiaArticulosVendidosListadoFiltros::operadoresParaCampo($campoActivo) as $opKey => $opLabel)
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
                       placeholder="SKU, descripción, PV, depósito…"
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
