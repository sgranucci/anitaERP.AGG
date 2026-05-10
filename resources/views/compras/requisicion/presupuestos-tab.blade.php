@php
    $tabPresupuestoEnum = \App\Models\Compras\Requisicion_Presupuesto::$enumEstado;
@endphp
<div class="form6" style="display:none;" id="solapa-presupuestos-requisicion"
    data-requisicion-id="{{ $data->id }}"
    data-url-index="{{ route('requisicion_presupuestos_index', ['requisicion' => $data->id]) }}"
    data-url-store="{{ route('requisicion_presupuesto_store', ['requisicion' => $data->id]) }}"
    data-url-show="{{ url('compras/requisicion/'.$data->id.'/presupuestos') }}"
    data-url-update="{{ url('compras/requisicion/'.$data->id.'/presupuestos') }}"
    data-url-destroy="{{ url('compras/requisicion/'.$data->id.'/presupuestos') }}"
    data-url-presupuesto-base="{{ url('compras/requisicion/'.$data->id.'/presupuestos') }}"
    data-csrf="{{ csrf_token() }}"
    data-readonly="{{ !empty($visualizar) ? '1' : '0' }}"
>
    <h5 class="mb-2">Presupuestos solicitados a proveedores</h5>
    <div class="alert alert-light border small mb-3" role="note">
        <p class="mb-2"><strong>¿Qué es esto?</strong> Aquí registrás las cotizaciones que pedís a cada proveedor sobre los ítems de esta requisición (precios unitarios, condiciones de entrega, compra y pago, y archivos del proveedor).</p>
        <ul class="mb-2 pl-3">
            <li><strong>Nuevo presupuesto:</strong> elegí proveedor, fecha y completá las líneas con el precio cotizado por ítem (podés adjuntar PDF u otros archivos). Podés repetir el proceso para comparar varios proveedores.</li>
            <li><strong>Estados:</strong> <em>ACTIVO</em> y <em>SUSPENDIDO</em> para gestionar la negociación; <em>ELEGIDO</em> marca la cotización seleccionada (solo puede haber una elegida a la vez).</li>
            <li><strong>PDF / imprimir:</strong> desde la grilla o el detalle podés descargar el PDF del presupuesto o abrir el formulario para imprimir. El PDF de la requisición incluirá un apartado con todas las cotizaciones cargadas, si las hay.</li>
        </ul>
        <p class="mb-0 text-muted">Los ítems cotizados corresponden a las líneas de artículos ya cargadas en la solapa «Datos principales».</p>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm table-striped" id="tabla-lista-presupuestos">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Estado</th>
                    <th>Archivos</th>
                    @if(empty($visualizar))
                    <th style="width:120px;"></th>
                    @else
                    <th style="width:72px;"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                <tr id="fila-carga-presupuestos"><td colspan="{{ empty($visualizar) ? '5' : '5' }}" class="text-center text-muted">Cargue la solapa para ver los presupuestos…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Modal presupuesto -->
    <div class="modal fade" id="modalPresupuestoRequisicion" tabindex="-1" role="dialog" aria-labelledby="modalPresupuestoRequisicionTitulo" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPresupuestoRequisicionTitulo">Presupuesto de proveedor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="presupuesto_edit_id" value="">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="presupuesto_fecha">Fecha del presupuesto</label>
                            <input type="date" class="form-control" id="presupuesto_fecha">
                        </div>
                        <div class="form-group col-md-8">
                            <label for="presupuesto_proveedor_id">Proveedor cotizado</label>
                            <select class="form-control" id="presupuesto_proveedor_id" {{ !empty($visualizar) ? 'disabled' : '' }}>
                                <option value="">Seleccione…</option>
                                @foreach ($proveedor_query as $pv)
                                    <option value="{{ $pv->id }}">{{ $pv->codigo }} — {{ $pv->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="presupuesto_condicionentrega_id">Condición de entrega</label>
                        <select class="form-control" id="presupuesto_condicionentrega_id" {{ !empty($visualizar) ? 'disabled' : '' }}>
                            <option value="">—</option>
                            @foreach (($condicionentrega_query ?? collect()) as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="presupuesto_condicioncompra_id">Condición de compra</label>
                        <select class="form-control" id="presupuesto_condicioncompra_id" {{ !empty($visualizar) ? 'disabled' : '' }}>
                            <option value="">—</option>
                            @foreach (($condicioncompra_query ?? collect()) as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="presupuesto_condicionpago_id">Condición de pago</label>
                        <select class="form-control" id="presupuesto_condicionpago_id" {{ !empty($visualizar) ? 'disabled' : '' }}>
                            <option value="">—</option>
                            @foreach (($condicionpago_query ?? collect()) as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="presupuesto_estado">Estado del presupuesto</label>
                        <select class="form-control" id="presupuesto_estado" {{ !empty($visualizar) ? 'disabled' : '' }}>
                            @foreach ($tabPresupuestoEnum as $ev)
                                <option value="{{ $ev['nombre'] }}">{{ $ev['nombre'] }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Solo puede haber un presupuesto <strong>ELEGIDO</strong>; al elegir uno, el anterior deja de estar elegido.</small>
                    </div>

                    <h6 class="mt-3">Precios cotizados por línea de la requisición</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="tabla-lineas-presupuesto">
                            <thead>
                                <tr>
                                    <th>Artículo</th>
                                    <th>Descripción</th>
                                    <th>Cant.</th>
                                    <th>Precio req.</th>
                                    <th>Precio cotizado</th>
                                    <th>Obs.</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <h6 class="mt-3">Archivos del presupuesto</h6>
                    @if(empty($visualizar))
                    <div class="form-group">
                        <input type="file" class="form-control-file" id="presupuesto_archivos_input" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.xlsx,.xls,.doc,.docx">
                        <small class="form-text text-muted">PDF, imágenes u Office. Vista previa en la grilla antes de guardar.</small>
                    </div>
                    @endif
                    <div class="row" id="presupuesto-preview-nuevos-archivos"></div>
                    <div id="presupuesto-archivos-existentes" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="presupuesto_abrir_pdf" class="btn btn-sm btn-outline-danger d-none" target="_blank" rel="noopener noreferrer">Descargar PDF</a>
                    <a href="#" id="presupuesto_abrir_impresion" class="btn btn-sm btn-outline-secondary d-none" target="_blank" rel="noopener noreferrer">Formulario para imprimir</a>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    @if(empty($visualizar))
                    <button type="button" class="btn btn-primary" id="presupuesto_btn_guardar">Guardar</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
