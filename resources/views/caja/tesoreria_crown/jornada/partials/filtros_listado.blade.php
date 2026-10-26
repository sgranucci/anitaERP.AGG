@php
    $f = $filtros ?? [];
    $estado = (string) ($f['estado'] ?? '');
@endphp
<div class="collapse border-bottom" id="panel-filtros-tesoreria-crown-jornada" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida_jornada" value="">
    <input type="hidden" name="sala_id" value="{{ (int) ($sala?->id ?? 0) }}">
    <div class="card-body bg-light py-2">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Cajero</label>
                <input type="text" id="filtro_valor_panel" class="form-control form-control-sm" value="{{ $f['valor'] ?? '' }}" autocomplete="off">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="fecha_desde">Fecha desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" value="{{ $f['fecha_desde'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="fecha_hasta">Fecha hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" value="{{ $f['fecha_hasta'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="estado">Estado</label>
                <select name="estado" id="estado" class="form-control form-control-sm">
                    <option value="" @selected($estado === '')>Todas</option>
                    <option value="abierta" @selected($estado === 'abierta')>Abierta</option>
                    <option value="cerrada" @selected($estado === 'cerrada')>Cerrada</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-sm btn-info" data-aplicar-filtros-panel>Aplicar filtros</button>
    </div>
</div>
