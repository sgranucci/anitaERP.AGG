{{-- Modales: cabecera de comprobante a venir y edición de cuotas --}}
<div class="modal fade" id="modalOcComprobanteCabecera" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Comprobante a venir</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="oc_comp_cab_idx" value="-1">
                <div class="form-group row">
                    <label class="col-md-3">Tipo</label>
                    <div class="col-md-9">
                        <select id="oc_comp_cab_tipo" class="form-control">
                            @foreach ($tipos_comprobante as $tc)
                                <option value="{{ $tc['valor'] }}">{{ $tc['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-3">Fecha vencimiento (cabecera)</label>
                    <div class="col-md-4">
                        <input type="date" id="oc_comp_cab_fecha" class="form-control">
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-3">Monto total</label>
                    <div class="col-md-4">
                        <input type="number" step="0.01" id="oc_comp_cab_monto" class="form-control">
                    </div>
                    <label class="col-md-2">Moneda</label>
                    <div class="col-md-3">
                        <select id="oc_comp_cab_moneda" class="form-control">
                            @foreach ($moneda_query as $moneda)
                                <option value="{{ $moneda->id }}">{{ $moneda->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-3">Detalle (opcional)</label>
                    <div class="col-md-9">
                        <textarea id="oc_comp_cab_detalle" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="oc_comp_cab_guardar">Guardar comprobante</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalOcComprobanteCuotas" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cuotas del comprobante</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="oc_cuotas_comp_idx" value="">
                <div class="alert alert-light border small mb-3 py-2" id="oc_cuotas_comp_detalle_wrap">
                    <span class="text-muted d-block mb-1">Detalle del comprobante (cabecera)</span>
                    <div id="oc_cuotas_comp_detalle_text" class="mb-0 small">—</div>
                </div>
                <p class="text-muted small mb-3">Arme las cuotas con importe, vencimiento, forma de pago y misma moneda que el comprobante a venir (no la de la orden de compra en general). La suma de importes debe coincidir con el monto total de ese comprobante.</p>

                <ul class="nav nav-pills mb-3" id="oc-cuotas-pills" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#oc-cuotas-pane-cond" role="tab">Por condición de pago</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#oc-cuotas-pane-manual" role="tab">Manual (cantidad)</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="oc-cuotas-pane-cond">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Condición de pago</label>
                                <select id="oc_cuotas_condicionpago_id" class="form-control">
                                    <option value="">—</option>
                                    @foreach ($condicionpago_query as $cp)
                                        <option value="{{ $cp->id }}">{{ $cp->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Fecha base</label>
                                <input type="date" id="oc_cuotas_fecha_base" class="form-control" value="{{ old('fecha', (isset($data) && $data && $data->fecha) ? substr($data->fecha, 0, 10) : date('Y-m-d')) }}">
                            </div>
                            <div class="form-group col-md-2">
                                <label>Monto</label>
                                <input type="number" step="0.01" id="oc_cuotas_monto_calc" class="form-control">
                            </div>
                            <div class="form-group col-md-2">
                                <label>Moneda <small class="text-muted font-weight-normal">(del comprobante)</small></label>
                                <select id="oc_cuotas_moneda_calc" class="form-control" title="Moneda del comprobante a venir; para cambiarla edite la cabecera del comprobante.">
                                    @foreach ($moneda_query as $moneda)
                                        <option value="{{ $moneda->id }}">{{ $moneda->abreviatura }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-12">
                                <button type="button" class="btn btn-outline-danger btn-sm" id="oc_cuotas_btn_generar_cond">Generar cuotas según condición</button>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="oc-cuotas-pane-manual">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-2">
                                <label>Cantidad (N)</label>
                                <input type="number" min="1" max="60" id="oc_cuotas_cantidad_manual" class="form-control" value="1" title="Número de cuotas a generar">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Fecha 1ª cuota <small class="text-muted font-weight-normal">(mensual)</small></label>
                                <input type="date" id="oc_cuotas_fecha_primera_manual" class="form-control" title="Vencimiento de la primera cuota; las siguientes suman 1 mes">
                            </div>
                            <div class="form-group col-md-7">
                                <label class="d-block">&nbsp;</label>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="oc_cuotas_btn_crear_manual">Crear / reemplazar (misma fecha)</button>
                                <button type="button" class="btn btn-outline-danger btn-sm ml-1" id="oc_cuotas_btn_mensual">Generar cuotas mensuales</button>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">Usa el <strong>Monto</strong> de la solapa «Por condición de pago» (mismo comprobante). Partes iguales: <em>misma fecha</em> repite el vencimiento en todas las filas; <em>mensual</em> aplica +1 mes entre cuotas. Luego puede editar importes y fechas en la tabla.</p>
                    </div>
                </div>

                <hr>
                <h6 class="mb-2">Detalle de cuotas</h6>
                <div class="alert alert-light border small mb-3 py-2" id="oc_cuotas_resumen_wrap">
                    <div class="form-row">
                        <div class="col-md-4">
                            <span class="text-muted">Monto comprobante <small class="d-block font-weight-normal">(moneda del comprobante)</small></span>
                            <div class="font-weight-bold" id="oc_cuotas_resumen_monto_ref">—</div>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted">Total cuotas cargadas <small class="d-block font-weight-normal">(misma moneda)</small></span>
                            <div class="font-weight-bold" id="oc_cuotas_resumen_total_cuotas">—</div>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted">Falta cubrir</span>
                            <div class="font-weight-bold" id="oc_cuotas_resumen_falta">—</div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-2">Al agregar una cuota, el importe sugerido es el pendiente; puede cambiarlo si seguirá cargando más cuotas.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="oc_tabla_cuotas_editable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Vencimiento</th>
                                <th>Monto</th>
                                <th class="text-nowrap">Moneda <small class="text-muted font-weight-normal">(del comprobante)</small></th>
                                <th>Forma de pago</th>
                                <th>Detalle</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="oc_cuotas_tbody"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" id="oc_cuotas_agregar_fila">+ Agregar cuota</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-danger" id="oc_cuotas_guardar">Guardar cuotas</button>
            </div>
        </div>
    </div>
</div>
