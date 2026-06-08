@php
    $camposFiltro = $camposFiltro ?? \App\Support\Sala\RequisicionSalaListadoFiltros::CAMPOS;
    $filtros = $filtros ?? [];
@endphp
<div class="collapse{{ \App\Support\Sala\RequisicionSalaListadoFiltros::tieneCriteriosAplicados($filtros) ? ' show' : '' }}" id="panel-filtros-requisicion-sala">
    <div class="card-body border-bottom bg-light py-3">
        <div class="row">
            <div class="col-md-3">
                <label for="filtro_modo">Modo</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="todos" {{ ($filtros['modo'] ?? '') === 'todos' ? 'selected' : '' }}>Buscar en todos los campos</option>
                    <option value="campo" {{ ($filtros['modo'] ?? '') === 'campo' ? 'selected' : '' }}>Buscar por campo</option>
                </select>
            </div>
            <div class="col-md-3 filtro-campo-panel">
                <label for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach ($camposFiltro as $key => $def)
                        <option value="{{ $key }}" {{ ($filtros['campo'] ?? '') === $key ? 'selected' : '' }}>{{ $def['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 filtro-campo-panel">
                <label for="filtro_operador">Operador</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"></select>
            </div>
            <div class="col-md-2 filtro-campo-panel">
                <label for="filtro_valor_panel">Valor</label>
                <input type="text" name="filtro_valor_panel" id="filtro_valor_panel" class="form-control form-control-sm" value="{{ $filtros['valor'] ?? '' }}">
            </div>
            <div class="col-md-2 filtro-campo-panel filtro-valor-hasta-wrap d-none">
                <label for="filtro_valor_hasta">Hasta</label>
                <input type="text" name="filtro_valor_hasta" id="filtro_valor_hasta" class="form-control form-control-sm" value="{{ $filtros['valor_hasta'] ?? '' }}">
            </div>
        </div>
        <div class="mt-2">
            <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
            <a href="{{ $limpiarUrl ?? route('consultar_requisicion_sala') }}" class="btn btn-outline-secondary btn-sm">Limpiar filtros</a>
        </div>
    </div>
</div>
