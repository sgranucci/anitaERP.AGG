@php
    $f = $filtros ?? [];
    $hoy = \App\Support\Ventas\ListadoRepartoFechaEntregaSupport::fechaHoy();
@endphp
@include('includes.listado.filtros_estilos_activos')
<div class="border-bottom" data-listado-filtros-externos>
    <div class="card-body bg-white py-2 text-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3 col-sm-6 mb-2 mb-md-0">
                <label class="small mb-1" for="filtro_reparto">Repartos</label>
                <input type="text"
                       name="filtro_reparto"
                       id="filtro_reparto"
                       class="form-control form-control-sm"
                       value="{{ $f['filtro_reparto'] ?? '' }}"
                       placeholder="Ej: 1,3,5 &oacute; 10/20"
                       autocomplete="off"
                       title="Coma = lista; barra / = rango. Vac&iacute;o = todos.">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 mb-md-0">
                <label class="small mb-1" for="fecha_entrega_desde">Entrega desde</label>
                <input type="date"
                       name="fecha_entrega_desde"
                       id="fecha_entrega_desde"
                       class="form-control form-control-sm"
                       value="{{ $f['fecha_entrega_desde'] ?? $hoy }}"
                       data-fecha-hoy="{{ $hoy }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 mb-md-0">
                <label class="small mb-1" for="fecha_entrega_hasta">Entrega hasta</label>
                <input type="date"
                       name="fecha_entrega_hasta"
                       id="fecha_entrega_hasta"
                       class="form-control form-control-sm"
                       value="{{ $f['fecha_entrega_hasta'] ?? $hoy }}">
            </div>
            <div class="form-group col-md-auto mb-2 mb-md-0">
                <label class="small mb-1 d-block listado-filtros-label-spacer" aria-hidden="true">&nbsp;</label>
                <button type="submit" class="btn btn-outline-primary btn-sm" title="Aplicar reparto y fechas">
                    <i class="fa fa-filter"></i> Aplicar
                </button>
            </div>
        </div>
        <small class="form-text text-muted mb-0 mt-1">
            Repartos: lista con coma (1,3,5); rango con barra (10/20). Vac&iacute;o = todos.
        </small>
    </div>
</div>
