@php
    $chequesMovimiento = isset($data) ? ($data->cheques ?? collect()) : collect();
    $chequesEmitidos = $chequesMovimiento->where('origen', 'E')->whereNull('cheque_reemplaza_id')->values();
    $chequesRecibidos = $chequesMovimiento->where('origen', 'R')->whereNull('cheque_reemplaza_id')->values();
    $chequesReemplazo = $chequesMovimiento->whereNotNull('cheque_reemplaza_id')->values();
@endphp
<div class="card card-outline card-info form2 mb-0 border-0 shadow-none" style="display: none">
    <div class="card-body">
        @include('includes.tabs-activas-estilos')
        <div class="tabs-activas mb-3">
        <ul class="nav nav-tabs" id="tabs-cheques-ingresoegreso" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-cheques-emitidos" data-toggle="tab" href="#panel-cheques-emitidos" role="tab">
                    Cheques emitidos (propios)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-cheques-recibidos" data-toggle="tab" href="#panel-cheques-recibidos" role="tab">
                    Cheques recibidos (terceros)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-cheques-reemplazo" data-toggle="tab" href="#panel-cheques-reemplazo" role="tab">
                    Anulación / reemplazo
                </a>
            </li>
        </ul>
        </div>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="panel-cheques-emitidos" role="tabpanel">
                <p class="text-muted small">
                    Emisi&oacute;n de cheques propios. Posdatados imputan a cheques diferidos si est&aacute; habilitado en config.
                </p>
                <table class="table table-sm table-bordered" id="cheque-emitido-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Cuenta banco</th>
                            <th>Chequera</th>
                            <th>Nro.</th>
                            <th>F. pago</th>
                            <th>Car&aacute;cter</th>
                            <th>A nombre de</th>
                            <th>Mon.</th>
                            <th>Monto</th>
                            <th>Cotiz.</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-cheque-emitido-table">
                        @foreach ($chequesEmitidos as $cheque)
                            <tr class="item-cheque-emitido">
                                <td>
                                    <input type="hidden" name="cheque_emitido_ids[]" class="cheque_emitido_id" value="{{ $cheque->id }}">
                                    <input type="hidden" name="cuentacaja_emitido_ids[]" class="cuentacaja_emitido_id" value="{{ $cheque->cuentacaja_id }}">
                                    <button type="button" class="btn-accion-tabla consultacuentacaja_emitido tooltipsC" title="Consulta cuenta banco">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="codigo_emitido form-control d-inline-block" style="width:90px" name="codigo_emitido[]" value="{{ $cheque->cuentacajas->codigo ?? '' }}">
                                    <input type="text" class="nombre_emitido form-control d-inline-block" style="width:140px" readonly value="{{ $cheque->cuentacajas->nombre ?? '' }}">
                                </td>
                                <td>
                                    <select name="chequera_emitido_ids[]" class="form-control chequera_emitido_id">
                                        <option value="">--</option>
                                        @foreach ($chequera_query as $ch)
                                            <option value="{{ $ch->id }}" @if((int) $ch->id === (int) $cheque->chequera_id) selected @endif>{{ $ch->nombre }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="numerocheque_emitidos[]" class="form-control numerocheque_emitido" value="{{ $cheque->numerocheque }}"></td>
                                <td><input type="date" name="fechapago_emitidos[]" class="form-control fechapago_emitido" value="{{ $cheque->fechapago }}"></td>
                                <td>
                                    <select name="caracter_emitidos[]" class="form-control caracter_emitido">
                                        @foreach ($caracter_enum as $car)
                                            @if ($car['valor'] !== 'R')
                                                <option value="{{ $car['valor'] }}" @if($car['valor'] === $cheque->caracter) selected @endif>{{ $car['nombre'] }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="anombrede_emitidos[]" class="form-control anombrede_emitido" value="{{ $cheque->anombrede }}"></td>
                                <td>
                                    <select name="moneda_emitido_ids[]" class="form-control moneda_emitido_id">
                                        @foreach ($moneda_query as $m)
                                            <option value="{{ $m->id }}" @if((int) $m->id === (int) $cheque->moneda_id) selected @endif>{{ $m->abreviatura }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" name="montocheque_emitidos[]" class="form-control montocheque_emitido" min="0" step="0.01" value="{{ $cheque->monto }}"></td>
                                <td><input type="number" name="cotizacioncheque_emitidos[]" class="form-control cotizacioncheque_emitido" step="0.0001" value="{{ $cheque->cotizacion }}"></td>
                                <td>
                                    <button type="button" class="btn-accion-tabla eliminar_cheque_emitido tooltipsC" title="Eliminar">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @include('caja.ingresoegreso.template_cheque_emitido')
                <button type="button" id="agrega_renglon_cheque_emitido" class="btn btn-danger btn-sm">+ Cheque emitido</button>
                <div class="form-group row totales-por-moneda-cheque-emitido mt-2"></div>
            </div>
            <div class="tab-pane fade" id="panel-cheques-recibidos" role="tabpanel">
                <h3>Cheques recibidos</h3>
                <table class="table table-sm table-bordered" id="cheque-recibido-table">
                    <thead style="background:#85C1E9;color:#17202A;">
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
                                            <option value="{{ $m->id }}" @if((int) $m->id === (int) $cheque->moneda_id) selected @endif>{{ $m->abreviatura }}</option>
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
            <div class="tab-pane fade" id="panel-cheques-reemplazo" role="tabpanel">
                <p class="text-muted small">
                    Anula un cheque existente y registra el reemplazo (emitido o recibido).
                </p>
                <table class="table table-sm table-bordered" id="cheque-reemplazo-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Cheque a anular</th>
                            <th>Tipo reemplazo</th>
                            <th>Nro. nuevo</th>
                            <th>F. pago</th>
                            <th>Cuenta / Banco</th>
                            <th>Monto</th>
                            <th>Mon.</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-cheque-reemplazo-table">
                        @foreach ($chequesReemplazo as $cheque)
                            <tr class="item-cheque-reemplazo">
                                <td>
                                    <input type="hidden" name="cheque_anulado_ids[]" class="cheque_anulado_id" value="{{ $cheque->cheque_reemplaza_id }}">
                                    <input type="text" class="form-control numerocheque_anulado" readonly value="{{ $cheque->chequeReemplazado->numerocheque ?? '' }}">
                                </td>
                                <td>
                                    <select name="origen_reemplazo[]" class="form-control origen_reemplazo">
                                        <option value="E" @if($cheque->origen === 'E') selected @endif>Emitido</option>
                                        <option value="R" @if($cheque->origen === 'R') selected @endif>Recibido</option>
                                    </select>
                                </td>
                                <td><input type="text" name="numerocheque_reemplazo[]" class="form-control numerocheque_reemplazo" value="{{ $cheque->numerocheque }}"></td>
                                <td><input type="date" name="fechapago_reemplazo[]" class="form-control fechapago_reemplazo" value="{{ $cheque->fechapago }}"></td>
                                <td>
                                    <input type="hidden" name="cuentacaja_reemplazo_ids[]" class="cuentacaja_reemplazo_id" value="{{ $cheque->cuentacaja_id }}">
                                    <input type="hidden" name="banco_reemplazo_ids[]" class="banco_reemplazo_id" value="{{ $cheque->banco_id }}">
                                    <input type="hidden" name="chequera_reemplazo_ids[]" class="chequera_reemplazo_id" value="{{ $cheque->chequera_id }}">
                                    <input type="text" class="form-control detalle_reemplazo_cuenta" readonly value="{{ $cheque->cuentacajas->codigo ?? ($cheque->bancos->nombre ?? '') }}">
                                </td>
                                <td><input type="number" name="montocheque_reemplazo[]" class="form-control montocheque_reemplazo" value="{{ $cheque->monto }}"></td>
                                <td>
                                    <select name="moneda_reemplazo_ids[]" class="form-control moneda_reemplazo_id">
                                        @foreach ($moneda_query as $m)
                                            <option value="{{ $m->id }}" @if((int) $m->id === (int) $cheque->moneda_id) selected @endif>{{ $m->abreviatura }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="cotizacioncheque_reemplazo[]" class="cotizacioncheque_reemplazo" value="{{ $cheque->cotizacion }}">
                                </td>
                                <td>
                                    <button type="button" class="btn-accion-tabla eliminar_cheque_reemplazo tooltipsC" title="Eliminar">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @include('caja.ingresoegreso.template_cheque_reemplazo')
                <button type="button" id="agrega_renglon_cheque_reemplazo" class="btn btn-danger btn-sm">+ Anulaci&oacute;n / reemplazo</button>
            </div>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultabanco')
