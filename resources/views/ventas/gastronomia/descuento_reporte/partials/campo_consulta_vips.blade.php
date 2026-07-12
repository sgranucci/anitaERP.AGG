@php
    $vipsIniciales = $vips_iniciales ?? [];
    $idsIniciales = collect($vipsIniciales)->pluck('id')->filter()->implode(',');
    $filtrosVip = $filtros ?? [];
    $vipCodigoDesde = $filtrosVip['vip_codigo_desde'] ?? '';
    $vipCodigoHasta = $filtrosVip['vip_codigo_hasta'] ?? '';
@endphp
<div class="form-group row mb-2" id="tm-vip-descuento-reporte-campo">
    <label class="col-lg-2 control-label text-right pr-2 requerido" id="label-seleccion-vip-reporte">Clientes VIP</label>
    <div class="col-lg-8">
        <input type="hidden" name="vips_descuento_ids" id="vips_descuento_ids" value="{{ $idsIniciales }}">

        <p class="text-muted small mb-2 font-weight-bold">Clientes VIP puntuales</p>
        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 6px;">
            <button type="button" title="Consultar clientes VIP" class="btn btn-outline-secondary btn-sm consultavip-reporte" data-destino="seleccion">
                <i class="fa fa-search"></i>
            </button>
            <input type="text"
                class="form-control form-control-sm codigovip-reporte"
                id="codigovip_reporte"
                value=""
                placeholder="C&oacute;d. Anita"
                autocomplete="off"
                style="max-width: 120px;">
            <input type="text"
                class="form-control form-control-sm nombrevip-reporte flex-grow-1"
                id="nombrevip_reporte"
                value=""
                placeholder="Nombre del cliente VIP"
                readonly>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-vip-reporte" title="Agregar cliente VIP a la lista">
                <i class="fa fa-plus"></i> Agregar
            </button>
        </div>

        <div class="border rounded bg-light px-3 py-2 mb-2" id="bloque-rango-vip-reporte">
            <p class="text-muted small mb-2 mb-md-1 font-weight-bold">Rango por c&oacute;digo Anita del VIP</p>
            <div class="form-row align-items-center">
                <div class="col-md-5 mb-2 mb-md-0">
                    <label for="vip_codigo_desde" class="small text-muted mb-1 d-block">Desde c&oacute;digo</label>
                    <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                        <button type="button"
                            title="Elegir cliente VIP inicial del rango"
                            class="btn btn-outline-secondary btn-sm consultavip-reporte"
                            data-destino="rango_desde">
                            <i class="fa fa-search"></i>
                        </button>
                        <input type="text"
                            name="vip_codigo_desde"
                            id="vip_codigo_desde"
                            class="form-control form-control-sm codigovip-rango-desde"
                            value="{{ $vipCodigoDesde }}"
                            placeholder="Ej. 1"
                            autocomplete="off"
                            style="max-width: 110px;">
                        <input type="text"
                            class="form-control form-control-sm nombrevip-rango-desde flex-grow-1"
                            id="nombrevip_rango_desde"
                            value=""
                            placeholder="Nombre"
                            readonly>
                    </div>
                </div>
                <div class="col-md-5 mb-2 mb-md-0">
                    <label for="vip_codigo_hasta" class="small text-muted mb-1 d-block">Hasta c&oacute;digo</label>
                    <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                        <button type="button"
                            title="Elegir cliente VIP final del rango"
                            class="btn btn-outline-secondary btn-sm consultavip-reporte"
                            data-destino="rango_hasta">
                            <i class="fa fa-search"></i>
                        </button>
                        <input type="text"
                            name="vip_codigo_hasta"
                            id="vip_codigo_hasta"
                            class="form-control form-control-sm codigovip-rango-hasta"
                            value="{{ $vipCodigoHasta }}"
                            placeholder="Ej. 20"
                            autocomplete="off"
                            style="max-width: 110px;">
                        <input type="text"
                            class="form-control form-control-sm nombrevip-rango-hasta flex-grow-1"
                            id="nombrevip_rango_hasta"
                            value=""
                            placeholder="Nombre"
                            readonly>
                    </div>
                </div>
                <div class="col-md-2 text-md-right">
                    <label class="small text-muted mb-1 d-none d-md-block">&nbsp;</label>
                    <button type="button" class="btn btn-outline-primary btn-sm btn-block" id="btn-agregar-rango-vip-reporte" title="Agregar todos los clientes VIP del rango a la lista">
                        <i class="fa fa-plus"></i> Incluir rango
                    </button>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-2">
                Incluye todos los c&oacute;digos num&eacute;ricos entre <em>desde</em> y <em>hasta</em>
                (puede consultar sin pulsar Incluir rango: al consultar se aplican VIP puntuales + rango).
                Si deja uno solo, se toma como c&oacute;digo &uacute;nico.
            </p>
        </div>

        <p class="text-muted small mb-2" id="ayuda-seleccion-vip-reporte">
            Cargue los <strong>clientes VIP</strong> beneficiarios del canje de marketing asignados en la cuenta.
            El reporte agrupa por cliente VIP las ventas del per&iacute;odo; opcionalmente puede acotar por c&oacute;digo de descuento en el filtro de abajo.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="tabla-vips-seleccionados-reporte">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 100px;">C&oacute;digo</th>
                        <th>Cliente VIP</th>
                        <th style="width: 70px;" class="text-center"></th>
                    </tr>
                </thead>
                <tbody id="tbody-vips-seleccionados-reporte">
                    @foreach ($vipsIniciales as $vip)
                        <tr data-id="{{ $vip['id'] ?? '' }}">
                            <td>{{ $vip['codigo'] ?? '' }}</td>
                            <td>{{ $vip['nombre'] ?? '' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-xs btn-quitar-vip-reporte" title="Quitar">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (($vipsIniciales ?? []) === [] && $vipCodigoDesde === '' && $vipCodigoHasta === '')
            <p class="text-muted small mb-0 mt-1" id="aviso-sin-vips-reporte">Sin clientes VIP cargados ni rango definido: al consultar se incluyen todos los VIP con ventas.</p>
        @else
            <p class="text-muted small mb-0 mt-1" id="aviso-sin-vips-reporte" style="display: none;">Sin clientes VIP cargados ni rango definido: al consultar se incluyen todos los VIP con ventas.</p>
        @endif
    </div>
</div>
