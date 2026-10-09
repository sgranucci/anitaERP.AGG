@php
    $chequesMovimiento = isset($data) ? ($data->cheques ?? collect()) : collect();
    $chequesEmitidos = $chequesMovimiento->where('origen', 'E')->whereNull('cheque_reemplaza_id')->values();
    $chequesRecibidos = $chequesMovimiento->where('origen', 'R')->whereNull('cheque_reemplaza_id')->values();
    if ($chequesEmitidos->isEmpty()) {
        $chequesEmitidos = \App\Support\Caja\ChequeFormOldInputSupport::emitidosDesdeOld();
    }
    if ($chequesRecibidos->isEmpty()) {
        $chequesRecibidos = \App\Support\Caja\ChequeFormOldInputSupport::recibidosDesdeOld();
    }
@endphp
<div class="card card-outline card-info form3 mb-0 border-0 shadow-none" style="display: none">
    <div class="card-body">
        @include('includes.tabs-activas-estilos')
        <div class="tabs-activas mb-3">
        <ul class="nav nav-tabs" id="tabs-cheques-pagoproveedor" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#panel-pp-cheques-emitidos" role="tab">
                    Cheques emitidos (propios)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#panel-pp-cheques-recibidos" role="tab">
                    Cheques de terceros a entregar
                </a>
            </li>
        </ul>
        </div>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="panel-pp-cheques-emitidos" role="tabpanel">
                @include('caja.ingresoegreso.partials.tabla_cheques_emitidos', [
                    'chequesEmitidos' => $chequesEmitidos,
                    'ayudaNumeradorChequeEmitido' => 'El nro. sale del numerador Anita: cheque al d&iacute;a (BMC) o diferido (BMD) seg&uacute;n F. pago vs fecha de la OP.',
                ])
            </div>
            <div class="tab-pane fade" id="panel-pp-cheques-recibidos" role="tabpanel">
                <p class="text-muted small mb-2">
                    Entrega de cheques de terceros desde la <strong>cartera</strong>.
                    Carpeta verde / F1 o Enter en el n&uacute;mero: elige un valor existente (nro. interno Anita).
                    No cargue a mano un cheque que ya est&aacute; en cartera.
                </p>
                <style>
                    #cheque-recibido-table .form-control { height: 32px; padding: 2px 6px; }
                    #cheque-recibido-table .pp-cheque-banco { display: flex; align-items: center; gap: 4px; min-width: 18rem; }
                    #cheque-recibido-table .codigobanco_recibido { width: 4.2rem; flex: 0 0 4.2rem; }
                    #cheque-recibido-table .nombrebanco_recibido { flex: 1 1 auto; min-width: 9rem; }
                    #cheque-recibido-table .montocheque_recibido,
                    #cheque-recibido-table .cotizacioncheque_recibido { text-align: right; }
                    #cheque-recibido-table tfoot td { background: #e9ecef; border-top: 2px solid #ced4da; vertical-align: middle; }
                </style>
                <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2" id="cheque-recibido-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:9rem;">F. cheque</th>
                            <th>Banco</th>
                            <th style="width:7rem;">Nro.</th>
                            <th style="width:6rem;">Sucursal</th>
                            <th style="width:9rem;">Cuenta</th>
                            <th style="width:4.5rem;">Mon.</th>
                            <th class="text-right" style="width:8.5rem;">Monto</th>
                            <th class="text-right" style="width:5rem;">Cotiz.</th>
                            <th style="width:2rem;"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-cheque-recibido-table">
                        @foreach ($chequesRecibidos as $cheque)
                            <tr class="item-cheque-recibido {{ ! empty($cheque->nro_interno_anita) ? 'cheque-desde-cartera' : '' }}">
                                <td><input type="date" name="fechapago_recibidos[]" class="form-control fechapago_recibido" value="{{ $cheque->fechapago }}"></td>
                                <td>
                                    <div class="pp-cheque-banco d-flex align-items-center flex-nowrap">
                                        <input type="hidden" name="cheque_recibido_ids[]" class="cheque_recibido_id" value="{{ $cheque->id }}">
                                        <input type="hidden" name="nro_interno_anita_recibidos[]" class="nro_interno_anita_recibido" value="{{ $cheque->nro_interno_anita ?? '' }}">
                                        <input type="hidden" name="banco_recibido_ids[]" class="banco_recibido_id" value="{{ $cheque->banco_id }}">
                                        <button type="button" class="btn-accion-tabla consultachequecartera_recibido tooltipsC" title="Cartera (F1)">
                                            <i class="fa fa-folder-open text-success"></i>
                                        </button>
                                        <button type="button" class="btn-accion-tabla consultabanco_recibido tooltipsC" title="Consulta banco">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        <input type="text" class="codigobanco_recibido form-control" name="codigobanco_recibido[]" value="{{ $cheque->bancos->codigo ?? '' }}">
                                        <input type="text" class="nombrebanco_recibido form-control" readonly value="{{ $cheque->bancos->nombre ?? '' }}" title="{{ $cheque->bancos->nombre ?? '' }}">
                                    </div>
                                </td>
                                <td><input type="text" name="numerocheque_recibidos[]" class="form-control numerocheque_recibido" value="{{ $cheque->numerocheque }}"></td>
                                <td><input type="text" name="sucursalpago_recibidos[]" class="form-control sucursalpago_recibido" value="{{ $cheque->sucursalpago }}"></td>
                                <td><input type="text" name="cuentalibradora_recibidos[]" class="form-control cuentalibradora_recibido" value="{{ $cheque->cuentalibradora }}"></td>
                                <td>
                                    <select name="monedacheque_recibido_ids[]" class="form-control monedacheque_recibido_id">
                                        @foreach ($moneda_query as $m)
                                            <option value="{{ $m->id }}" @selected((int) $m->id === (int) $cheque->moneda_id)>{{ $m->abreviatura }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" name="montocheque_recibidos[]" class="form-control montocheque_recibido" min="0" step="0.01" value="{{ $cheque->monto }}"></td>
                                <td><input type="number" name="cotizacioncheque_recibidos[]" class="form-control cotizacioncheque_recibido" step="0.0001" value="{{ $cheque->cotizacion }}"></td>
                                <td class="text-center">
                                    <button type="button" class="btn-accion-tabla eliminar_cheque_recibido tooltipsC" title="Eliminar">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-right font-weight-bold text-secondary">Total cheques de terceros</td>
                            <td class="text-right font-weight-bold" id="pp-total-cheques-recibidos">0,00</td>
                            <td colspan="2" class="small text-muted" id="pp-total-cheques-recibidos-cant"></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
                @include('caja.ingresoegreso.template_cheque_recibido')
                <button type="button" id="agrega_renglon_cheque_recibido" class="btn btn-outline-secondary btn-sm">+ Rengl&oacute;n manual</button>
                <button type="button" id="agrega_renglon_cheque_cartera" class="btn btn-outline-success btn-sm">+ Desde cartera</button>
            </div>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultabanco')
@include('includes.caja.modalconsultachequera')
@include('includes.caja.modalconsultachequecartera')
