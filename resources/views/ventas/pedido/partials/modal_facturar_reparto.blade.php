<div class="modal fade" id="facturarRepartoPedidoModal" role="dialog" aria-labelledby="facturarRepartoPedidoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="facturarRepartoPedidoLabel">Facturar reparto</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="facturar-reparto-rango"></p>
                <div id="alert-facturar-reparto" class="alert alert-warning d-none mb-2" role="alert"></div>
                <style>
                    #tabla-facturar-reparto thead th {
                        position: sticky;
                        top: 0;
                        z-index: 2;
                        background: #85C1E9;
                    }
                    #tabla-facturar-reparto tfoot td {
                        position: sticky;
                        bottom: 0;
                        z-index: 2;
                        background: #F9E79F;
                    }
                </style>
                <div class="table-responsive mb-3" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0" id="tabla-facturar-reparto">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th class="text-right">Cajas</th>
                                <th class="text-right">Unidades</th>
                                <th class="text-right">Kilos</th>
                                <th class="text-right">Kilos pesados</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-facturar-reparto"></tbody>
                        <tfoot>
                            <tr id="tfoot-facturar-reparto" style="background:#F9E79F;font-weight:700;color:#17202A;">
                                <td colspan="2">Totales</td>
                                <td class="text-right" id="total-facturar-reparto-caja">0,00</td>
                                <td class="text-right" id="total-facturar-reparto-unidad">0,00</td>
                                <td class="text-right" id="total-facturar-reparto-kilo">0,00</td>
                                <td class="text-right" id="total-facturar-reparto-pesada">0,00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2 requerido">Tipo de transacción</label>
                    <div class="col-lg-7">
                        <select id="reparto_tipotransaccion_id" class="form-control">
                            <option value="">-- Seleccionar tipo de transacción --</option>
                            @foreach ($tipotransaccion_query as $tipo)
                                <option value="{{ $tipo->id }}"
                                    {{ (int) $tipo->id === (int) ($tipotransacciondefault_id ?? 0) ? 'selected' : '' }}>
                                    {{ $tipo->abreviatura }}-{{ $tipo->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2 requerido">Punto de venta factura</label>
                    <div class="col-lg-7">
                        <select id="reparto_puntoventa_id" class="form-control">
                            <option value="">-- Seleccionar punto de venta --</option>
                            @foreach ($puntoventa_query as $pv)
                                <option value="{{ $pv->id }}"
                                    {{ (int) $pv->id === (int) ($puntoventadefault_id ?? 0) ? 'selected' : '' }}>
                                    {{ $pv->codigo }}-{{ $pv->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2 requerido">Punto de venta del pedido</label>
                    <div class="col-lg-7">
                        <select id="reparto_puntoventaremito_id" class="form-control">
                            <option value="">-- Seleccionar punto de venta --</option>
                            @foreach ($puntoventa_query as $pv)
                                <option value="{{ $pv->id }}"
                                    {{ (int) $pv->id === (int) ($puntoventaremitodefault_id ?? 0) ? 'selected' : '' }}>
                                    {{ $pv->codigo }}-{{ $pv->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2">Actividad</label>
                    <div class="col-lg-7">
                        <input type="hidden" id="reparto_actividad_arca_id" value="">
                        <input type="text" id="reparto_actividad_arca_nombre" class="form-control" readonly value="">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
                <button type="button" id="aceptaFacturarRepartoPedido" class="btn btn-primary">Genera facturas</button>
            </div>
        </div>
    </div>
</div>
