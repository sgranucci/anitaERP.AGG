@php
    $panelId = 'panel-filtros-reporte-sueldos-definible';
@endphp
<div class="collapse {{ !empty($filtros['filtro_campo']) ? 'show' : '' }} border-bottom"
     id="{{ $panelId }}"
     data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-light py-2 text-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3 mb-2">
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
            <div class="form-group col-md-3 mb-2">
                <label class="small mb-1" for="filtro_operador">Operador</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm">
                    <option value="contiene" {{ ($filtros['filtro_operador'] ?? '') === 'contiene' ? 'selected' : '' }}>Contiene</option>
                    <option value="igual" {{ ($filtros['filtro_operador'] ?? '') === 'igual' ? 'selected' : '' }}>Igual</option>
                    <option value="empieza" {{ ($filtros['filtro_operador'] ?? '') === 'empieza' ? 'selected' : '' }}>Empieza</option>
                    <option value="termina" {{ ($filtros['filtro_operador'] ?? '') === 'termina' ? 'selected' : '' }}>Termina</option>
                    <option value="mayor" {{ ($filtros['filtro_operador'] ?? '') === 'mayor' ? 'selected' : '' }}>Mayor</option>
                    <option value="menor" {{ ($filtros['filtro_operador'] ?? '') === 'menor' ? 'selected' : '' }}>Menor</option>
                </select>
            </div>
            <div class="form-group col-md-4 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                {{-- Sin name: el valor lo envía solo #filtro_valor de la toolbar (evita pisar la búsqueda rápida). --}}
                <input type="text" id="filtro_valor_panel" class="form-control form-control-sm"
                       value="{{ $filtros['filtro_valor'] ?? '' }}"
                       placeholder="Texto o código"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-2 mb-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm btn-block" data-aplicar-filtros-panel="1">
                    Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
