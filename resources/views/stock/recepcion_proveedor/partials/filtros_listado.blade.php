<form method="GET" action="{{ route('recepcion_proveedor') }}" id="form-filtros-recepcion" class="collapse">
    <div class="card-body border-bottom bg-light">
        <div class="row">
            <div class="col-md-4">
                <label>Campo</label>
                <select name="filtro_campo" class="form-control form-control-sm">
                    <option value="">—</option>
                    @foreach($camposFiltro as $clave => $meta)
                    <option value="{{ $clave }}" @selected(($filtros['filtro_campo'] ?? '') === $clave)>{{ $meta['etiqueta'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label>Operador</label>
                <select name="filtro_operador" class="form-control form-control-sm">
                    <option value="contiene">Contiene</option>
                    <option value="igual" @selected(($filtros['filtro_operador'] ?? '') === 'igual')>Igual</option>
                </select>
            </div>
            <div class="col-md-4">
                <label>Valor</label>
                <input type="text" name="filtro_valor" id="filtro_valor_panel" class="form-control form-control-sm"
                    value="{{ $filtros['filtro_valor'] ?? '' }}">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
            </div>
        </div>
    </div>
</form>
