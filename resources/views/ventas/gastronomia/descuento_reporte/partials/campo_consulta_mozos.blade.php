@php
    $mozosIniciales = $mozos_iniciales ?? [];
    $idsIniciales = collect($mozosIniciales)->pluck('id')->filter()->implode(',');
    $filtrosMozo = $filtros ?? [];
    $mozoCodigoDesde = $filtrosMozo['mozo_codigo_desde'] ?? '';
    $mozoCodigoHasta = $filtrosMozo['mozo_codigo_hasta'] ?? '';
@endphp
<div class="form-group row mb-2" id="tm-mozo-descuento-reporte-campo">
    <label class="col-lg-2 control-label text-right pr-2 requerido" id="label-seleccion-mozo-reporte">Mozos</label>
    <div class="col-lg-8">
        <input type="hidden" name="mozos_descuento_ids" id="mozos_descuento_ids" value="{{ $idsIniciales }}">

        <p class="text-muted small mb-2 font-weight-bold">Mozos puntuales</p>
        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 6px;">
            <button type="button" title="Consultar mozos" class="btn btn-outline-secondary btn-sm consultamozo-reporte" data-destino="seleccion">
                <i class="fa fa-search"></i>
            </button>
            <input type="text"
                class="form-control form-control-sm codigomozo-reporte"
                id="codigomozo_reporte"
                value=""
                placeholder="C&oacute;d. mozo"
                autocomplete="off"
                style="max-width: 120px;">
            <input type="text"
                class="form-control form-control-sm nombremozo-reporte flex-grow-1"
                id="nombremozo_reporte"
                value=""
                placeholder="Nombre del mozo"
                readonly>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-mozo-reporte" title="Agregar mozo a la lista">
                <i class="fa fa-plus"></i> Agregar
            </button>
        </div>

        <div class="border rounded bg-light px-3 py-2 mb-2" id="bloque-rango-mozo-reporte">
            <p class="text-muted small mb-2 mb-md-1 font-weight-bold">Rango por c&oacute;digo de mozo</p>
            <div class="form-row align-items-center">
                <div class="col-md-5 mb-2 mb-md-0">
                    <label for="mozo_codigo_desde" class="small text-muted mb-1 d-block">Desde c&oacute;digo</label>
                    <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                        <button type="button"
                            title="Elegir mozo inicial del rango"
                            class="btn btn-outline-secondary btn-sm consultamozo-reporte"
                            data-destino="rango_desde">
                            <i class="fa fa-search"></i>
                        </button>
                        <input type="text"
                            name="mozo_codigo_desde"
                            id="mozo_codigo_desde"
                            class="form-control form-control-sm codigomozo-rango-desde"
                            value="{{ $mozoCodigoDesde }}"
                            placeholder="Ej. 1"
                            autocomplete="off"
                            style="max-width: 110px;">
                        <input type="text"
                            class="form-control form-control-sm nombremozo-rango-desde flex-grow-1"
                            id="nombremozo_rango_desde"
                            value=""
                            placeholder="Nombre"
                            readonly>
                    </div>
                </div>
                <div class="col-md-5 mb-2 mb-md-0">
                    <label for="mozo_codigo_hasta" class="small text-muted mb-1 d-block">Hasta c&oacute;digo</label>
                    <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                        <button type="button"
                            title="Elegir mozo final del rango"
                            class="btn btn-outline-secondary btn-sm consultamozo-reporte"
                            data-destino="rango_hasta">
                            <i class="fa fa-search"></i>
                        </button>
                        <input type="text"
                            name="mozo_codigo_hasta"
                            id="mozo_codigo_hasta"
                            class="form-control form-control-sm codigomozo-rango-hasta"
                            value="{{ $mozoCodigoHasta }}"
                            placeholder="Ej. 20"
                            autocomplete="off"
                            style="max-width: 110px;">
                        <input type="text"
                            class="form-control form-control-sm nombremozo-rango-hasta flex-grow-1"
                            id="nombremozo_rango_hasta"
                            value=""
                            placeholder="Nombre"
                            readonly>
                    </div>
                </div>
                <div class="col-md-2 text-md-right">
                    <label class="small text-muted mb-1 d-none d-md-block">&nbsp;</label>
                    <button type="button" class="btn btn-outline-primary btn-sm btn-block" id="btn-agregar-rango-mozo-reporte" title="Agregar todos los mozos del rango a la lista">
                        <i class="fa fa-plus"></i> Incluir rango
                    </button>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-2">
                Incluye todos los c&oacute;digos num&eacute;ricos entre <em>desde</em> y <em>hasta</em>
                (puede consultar sin pulsar Incluir rango: al consultar se aplican mozos puntuales + rango).
                Si deja uno solo, se toma como c&oacute;digo &uacute;nico.
            </p>
        </div>

        <p class="text-muted small mb-2" id="ayuda-seleccion-mozo-reporte">
            Cargue los <strong>mozos</strong> asignados en la cuenta al facturar con descuento.
            El reporte agrupa por mozo las ventas del per&iacute;odo; opcionalmente puede acotar por c&oacute;digo de descuento en el filtro de abajo.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="tabla-mozos-seleccionados-reporte">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 100px;">C&oacute;digo</th>
                        <th>Mozo</th>
                        <th style="width: 70px;" class="text-center"></th>
                    </tr>
                </thead>
                <tbody id="tbody-mozos-seleccionados-reporte">
                    @foreach ($mozosIniciales as $mozo)
                        <tr data-id="{{ $mozo['id'] ?? '' }}">
                            <td>{{ $mozo['codigo'] ?? '' }}</td>
                            <td>{{ $mozo['nombre'] ?? '' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-xs btn-quitar-mozo-reporte" title="Quitar">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (($mozosIniciales ?? []) === [] && $mozoCodigoDesde === '' && $mozoCodigoHasta === '')
            <p class="text-muted small mb-0 mt-1" id="aviso-sin-mozos-reporte">Sin mozos cargados ni rango definido: al consultar se incluyen todos los mozos con ventas.</p>
        @else
            <p class="text-muted small mb-0 mt-1" id="aviso-sin-mozos-reporte" style="display: none;">Sin mozos cargados ni rango definido: al consultar se incluyen todos los mozos con ventas.</p>
        @endif
    </div>
</div>
