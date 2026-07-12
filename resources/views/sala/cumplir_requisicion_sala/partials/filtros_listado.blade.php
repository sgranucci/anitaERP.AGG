<div class="collapse {{ \App\Support\Sala\CumplimientoRequisicionSalaListadoFiltros::tieneCriteriosAplicados($filtros ?? []) ? 'show' : '' }}" id="panel-filtros-cumplimiento-req-sala">
    <div class="card-body border-top bg-light py-3">
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="filtro_modo">Modo</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="todos" @selected(($filtros['modo'] ?? '') === 'todos')>Todos los campos</option>
                    <option value="campo" @selected(($filtros['modo'] ?? '') === 'campo')>Por campo</option>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach ($camposFiltro ?? [] as $key => $def)
                        <option value="{{ $key }}" @selected(($filtros['campo'] ?? '') === $key)>{{ $def['label'] ?? $key }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2">
                <label for="filtro_operador">Operador</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"></select>
            </div>
            <div class="form-group col-md-2">
                <label for="filtro_valor_panel">Valor</label>
                <input type="text" name="filtro_valor" id="filtro_valor_panel" class="form-control form-control-sm" value="{{ $filtros['valor'] ?? '' }}">
            </div>
            <div class="form-group col-md-2">
                <label for="filtro_valor2">Valor 2</label>
                <input type="text" name="filtro_valor2" id="filtro_valor2" class="form-control form-control-sm" value="{{ $filtros['valor2'] ?? '' }}">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
        <a href="{{ $limpiarUrl ?? route('cumplir_requisicion_sala') }}" class="btn btn-link btn-sm">Limpiar</a>
    </div>
</div>
