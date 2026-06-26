@php
    $clientesIniciales = $clientes_iniciales ?? [];
    $idsIniciales = collect($clientesIniciales)->pluck('id')->filter()->implode(',');
@endphp
<div class="form-group row mb-2" id="tm-cliente-descuento-reporte-campo">
    <label class="col-lg-2 control-label text-right pr-2 requerido" id="label-seleccion-cliente-reporte">Clientes internos de descuento</label>
    <div class="col-lg-8">
        <input type="hidden" name="clientes_descuento_ids" id="clientes_descuento_ids" value="{{ $idsIniciales }}">
        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 6px;">
            <button type="button" title="Consultar clientes internos" class="btn btn-outline-secondary btn-sm consultacliente-reporte">
                <i class="fa fa-search"></i>
            </button>
            <input type="text"
                class="form-control form-control-sm codigocliente-reporte"
                id="codigocliente_reporte"
                value=""
                placeholder="Cód. cliente"
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
        <p class="text-muted small mb-2" id="ayuda-seleccion-cliente-reporte">
            Cargue los <strong>clientes internos</strong> asignados en la cuenta al facturar con descuento (misma lógica que en POS).
            El reporte agrupa por ese cliente las ventas del período; opcionalmente puede acotar por código de descuento en el filtro de abajo.
            Use la lupa, o ingrese el código de cliente y pulse Agregar.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="tabla-clientes-seleccionados-reporte">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 100px;">Código</th>
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
        @if (($clientesIniciales ?? []) === [])
            <p class="text-muted small mb-0 mt-1" id="aviso-sin-clientes-reporte">Sin clientes internos cargados.</p>
        @endif
    </div>
</div>
