@php
    $clientesIniciales = $clientes_iniciales ?? [];
    $idsIniciales = collect($clientesIniciales)->pluck('id')->filter()->implode(',');
    $filtrosCliente = $filtros ?? [];
    $clienteCodigoDesde = $filtrosCliente['cliente_codigo_desde'] ?? '';
    $clienteCodigoHasta = $filtrosCliente['cliente_codigo_hasta'] ?? '';
@endphp
<div class="form-group row mb-2" id="tm-cliente-descuento-reporte-campo">
    <label class="col-lg-2 control-label text-right pr-2 requerido" id="label-seleccion-cliente-reporte">Clientes internos de descuento</label>
    <div class="col-lg-8">
        <input type="hidden" name="clientes_descuento_ids" id="clientes_descuento_ids" value="{{ $idsIniciales }}">

        <p class="text-muted small mb-2 font-weight-bold">Clientes puntuales</p>
        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 6px;">
            <button type="button" title="Consultar clientes internos" class="btn btn-outline-secondary btn-sm consultacliente-reporte" data-destino="seleccion">
                <i class="fa fa-search"></i>
            </button>
            <input type="text"
                class="form-control form-control-sm codigocliente-reporte"
                id="codigocliente_reporte"
                value=""
                placeholder="C&oacute;d. cliente"
                autocomplete="off"
                style="max-width: 120px;">
            <input type="text"
                class="form-control form-control-sm nombrecliente-reporte flex-grow-1"
                id="nombrecliente_reporte"
                value=""
                placeholder="Nombre del cliente interno"
                readonly>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-cliente-reporte" title="Agregar cliente a la lista">
                <i class="fa fa-plus"></i> Agregar
            </button>
        </div>

        <div class="border rounded bg-light px-3 py-2 mb-2" id="bloque-rango-cliente-reporte">
            <p class="text-muted small mb-2 mb-md-1 font-weight-bold">Rango por c&oacute;digo de cliente</p>
            <div class="form-row align-items-center">
                <div class="col-md-5 mb-2 mb-md-0">
                    <label for="cliente_codigo_desde" class="small text-muted mb-1 d-block">Desde c&oacute;digo</label>
                    <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                        <button type="button"
                            title="Elegir cliente inicial del rango"
                            class="btn btn-outline-secondary btn-sm consultacliente-reporte"
                            data-destino="rango_desde">
                            <i class="fa fa-search"></i>
                        </button>
                        <input type="text"
                            name="cliente_codigo_desde"
                            id="cliente_codigo_desde"
                            class="form-control form-control-sm codigocliente-rango-desde"
                            value="{{ $clienteCodigoDesde }}"
                            placeholder="Ej. 500"
                            autocomplete="off"
                            style="max-width: 110px;">
                        <input type="text"
                            class="form-control form-control-sm nombrecliente-rango-desde flex-grow-1"
                            id="nombrecliente_rango_desde"
                            value=""
                            placeholder="Nombre"
                            readonly>
                    </div>
                </div>
                <div class="col-md-5 mb-2 mb-md-0">
                    <label for="cliente_codigo_hasta" class="small text-muted mb-1 d-block">Hasta c&oacute;digo</label>
                    <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                        <button type="button"
                            title="Elegir cliente final del rango"
                            class="btn btn-outline-secondary btn-sm consultacliente-reporte"
                            data-destino="rango_hasta">
                            <i class="fa fa-search"></i>
                        </button>
                        <input type="text"
                            name="cliente_codigo_hasta"
                            id="cliente_codigo_hasta"
                            class="form-control form-control-sm codigocliente-rango-hasta"
                            value="{{ $clienteCodigoHasta }}"
                            placeholder="Ej. 520"
                            autocomplete="off"
                            style="max-width: 110px;">
                        <input type="text"
                            class="form-control form-control-sm nombrecliente-rango-hasta flex-grow-1"
                            id="nombrecliente_rango_hasta"
                            value=""
                            placeholder="Nombre"
                            readonly>
                    </div>
                </div>
                <div class="col-md-2 text-md-right">
                    <label class="small text-muted mb-1 d-none d-md-block">&nbsp;</label>
                    <button type="button" class="btn btn-outline-primary btn-sm btn-block" id="btn-agregar-rango-cliente-reporte" title="Agregar todos los clientes del rango a la lista">
                        <i class="fa fa-plus"></i> Incluir rango
                    </button>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-2">
                Incluye todos los c&oacute;digos num&eacute;ricos entre <em>desde</em> y <em>hasta</em>
                (puede consultar sin pulsar Incluir rango: al consultar se aplican clientes puntuales + rango).
                Si deja uno solo, se toma como c&oacute;digo &uacute;nico.
            </p>
        </div>

        <p class="text-muted small mb-2" id="ayuda-seleccion-cliente-reporte">
            Cargue los <strong>clientes internos</strong> asignados en la cuenta al facturar con descuento (misma l&oacute;gica que en POS).
            El reporte agrupa por ese cliente las ventas del per&iacute;odo; opcionalmente puede acotar por c&oacute;digo de descuento en el filtro de abajo.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="tabla-clientes-seleccionados-reporte">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 100px;">C&oacute;digo</th>
                        <th>Cliente interno</th>
                        <th style="width: 70px;" class="text-center"></th>
                    </tr>
                </thead>
                <tbody id="tbody-clientes-seleccionados-reporte">
                    @foreach ($clientesIniciales as $cli)
                        <tr data-id="{{ $cli['id'] ?? '' }}">
                            <td>{{ $cli['codigo'] ?? '' }}</td>
                            <td>{{ $cli['nombre'] ?? '' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-xs btn-quitar-cliente-reporte" title="Quitar">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (($clientesIniciales ?? []) === [] && $clienteCodigoDesde === '' && $clienteCodigoHasta === '')
            <p class="text-muted small mb-0 mt-1" id="aviso-sin-clientes-reporte">Sin clientes internos cargados ni rango definido.</p>
        @else
            <p class="text-muted small mb-0 mt-1" id="aviso-sin-clientes-reporte" style="display: none;">Sin clientes internos cargados ni rango definido.</p>
        @endif
    </div>
</div>
