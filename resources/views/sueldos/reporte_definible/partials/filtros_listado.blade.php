@php
    $panelId = 'panel-filtros-reporte-sueldos-definible';
@endphp
<div class="collapse {{ !empty($filtros['filtro_campo']) ? 'show' : '' }}" id="{{ $panelId }}">
    <div class="card-body border-top pt-3">
        <div class="form-row">
            <div class="form-group col-md-3">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    <option value="">— Todos —</option>
                    @foreach ($camposFiltro as $clave => $etiqueta)
                        <option value="{{ $clave }}" {{ ($filtros['filtro_campo'] ?? '') === $clave ? 'selected' : '' }}>
                            {{ $etiqueta }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3">
                <label class="small mb-1" for="filtro_operador">Operador</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm">
                    <option value="contiene" {{ ($filtros['filtro_operador'] ?? '') === 'contiene' ? 'selected' : '' }}>Contiene</option>
                    <option value="igual" {{ ($filtros['filtro_operador'] ?? '') === 'igual' ? 'selected' : '' }}>Igual</option>
                    <option value="empieza" {{ ($filtros['filtro_operador'] ?? '') === 'empieza' ? 'selected' : '' }}>Empieza</option>
                    <option value="termina" {{ ($filtros['filtro_operador'] ?? '') === 'termina' ? 'selected' : '' }}>Termina</option>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text" name="filtro_valor" id="filtro_valor_panel" class="form-control form-control-sm"
                       value="{{ $filtros['filtro_valor'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm btn-block">Aplicar filtros</button>
            </div>
        </div>
    </div>
</div>
