@php $f = $filtros ?? []; @endphp
<div class="collapse border-bottom" id="panel-filtros-rendicion-mv-caja" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-light py-2">
        <div class="form-row align-items-end">
            @include('includes.listado.filtro_empresa_asignada', ['f' => $f])
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="fecha_desde">Desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" value="{{ $f['fecha_desde'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="small mb-1" for="fecha_hasta">Hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" value="{{ $f['fecha_hasta'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 mb-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Aplicar filtros</button>
            </div>
        </div>
    </div>
</div>
