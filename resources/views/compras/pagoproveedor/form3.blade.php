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
<div class="card form3" style="display: none">
    <div class="card-body">
        <ul class="nav nav-tabs mb-3" role="tablist">
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
        <div class="tab-content">
            <div class="tab-pane fade show active" id="panel-pp-cheques-emitidos" role="tabpanel">
                <p class="text-muted small mb-2">
                    Emisi&oacute;n de cheques propios. Cuenta: c&oacute;digo + Enter · F1 o lupa.
                    El nro. sale del numerador Anita: cheque al d&iacute;a (BMC) o diferido (BMD) seg&uacute;n F. pago vs fecha de la OP.
                    Chequera: lupa o F1 (tipo, rango y estado).
                    Car&aacute;cter es el legal impreso (A la orden / No a la orden).
                    Para dep. y F&iacute;sico/Electr&oacute;nico van a Anita (cpro_para_dep / cpro_negociable).
                </p>
                <div class="table-responsive">
                <table class="table table-sm table-bordered" id="cheque-emitido-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:12%;">C&oacute;digo</th>
                            <th style="width:16%;">Descripci&oacute;n</th>
                            <th style="width:16%;">Chequera</th>
                            <th style="width:8%;">Nro.</th>
                            <th style="width:10%;">F. pago</th>
                            <th style="width:12%;">Car&aacute;cter / Anita</th>
                            <th>A nombre de</th>
                            <th style="width:6%;">Mon.</th>
                            <th style="width:9%;">Monto</th>
                            <th style="width:7%;">Cotiz.</th>
                            <th style="width:2%;"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-cheque-emitido-table">
                        @foreach ($chequesEmitidos as $cheque)
                            @include('caja.ingresoegreso.partials.fila_cheque_emitido', ['cheque' => $cheque])
                        @endforeach
                    </tbody>
                </table>
                </div>
                @include('caja.ingresoegreso.template_cheque_emitido')
                <button type="button" id="agrega_renglon_cheque_emitido" class="btn btn-danger btn-sm">+ Cheque emitido</button>
                <div class="form-group row totales-por-moneda-cheque-emitido mt-2"></div>
            </div>
            <div class="tab-pane fade" id="panel-pp-cheques-recibidos" role="tabpanel">
                <p class="text-muted small">Entrega de cheques de terceros (valores a depositar).</p>
                <table class="table table-sm" id="cheque-recibido-table">
                    <thead>
                        <tr>
                            <th>F. cheque</th>
                            <th>Banco</th>
                            <th>Nro.</th>
                            <th>Sucursal</th>
                            <th>Cuenta</th>
                            <th>Mon.</th>
                            <th>Monto</th>
                            <th>Cotiz.</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-cheque-recibido-table">
                        @foreach ($chequesRecibidos as $cheque)
                            <tr class="item-cheque-recibido">
                                <td><input type="date" name="fechapago_recibidos[]" class="form-control fechapago_recibido" value="{{ $cheque->fechapago }}"></td>
                                <td>
                                    <input type="hidden" name="cheque_recibido_ids[]" class="cheque_recibido_id" value="{{ $cheque->id }}">
                                    <input type="hidden" name="banco_recibido_ids[]" class="banco_recibido_id" value="{{ $cheque->banco_id }}">
                                    <button type="button" class="btn-accion-tabla consultabanco_recibido tooltipsC" title="Consulta banco">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="codigobanco_recibido form-control d-inline-block" style="width:70px" name="codigobanco_recibido[]" value="{{ $cheque->bancos->codigo ?? '' }}">
                                    <input type="text" class="nombrebanco_recibido form-control d-inline-block" style="width:140px" readonly value="{{ $cheque->bancos->nombre ?? '' }}">
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
                                <td>
                                    <button type="button" class="btn-accion-tabla eliminar_cheque_recibido tooltipsC" title="Eliminar">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @include('caja.ingresoegreso.template_cheque_recibido')
                <button type="button" id="agrega_renglon_cheque_recibido" class="btn btn-danger btn-sm">+ Cheque recibido</button>
                <div class="form-group row totales-por-moneda-cheque-recibido mt-2"></div>
            </div>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultabanco')
@include('includes.caja.modalconsultachequera')
