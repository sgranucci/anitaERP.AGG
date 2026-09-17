@php
    $chequesCob = $data->cheques ?? collect();
@endphp
<div class="card form3" style="display: none">
    <div class="card-body">
        <div class="border rounded p-2 mb-3" style="background:#f8f9fa;color:#1b2631;">
            <strong>Cheques recibidos</strong>
            <div class="small text-muted mb-0 mt-1">F1 o lupa en el banco; Enter resuelve el c&oacute;digo y avanza al n&uacute;mero. En el n&uacute;mero, F&iacute;s / eCh marca f&iacute;sico o e-cheq.</div>
        </div>
        <table class="table table-sm table-bordered table-hover" id="cobranza-cheque-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 9%;">Fecha</th>
                    <th style="min-width: 14rem;">Banco</th>
                    <th style="width: 12%;">Nro. cheque</th>
                    <th style="width: 7%;">Sucursal</th>
                    <th style="width: 9%;">Cuenta</th>
                    <th style="width: 6%;">Moneda</th>
                    <th class="text-right" style="width: 10%;">Monto</th>
                    <th class="text-right" style="width: 9%;">Cotizaci&oacute;n</th>
                    <th style="width: 5rem;"></th>
                </tr>
            </thead>
            <tbody id="tbody-cobranza-cheque-table" class="container-cheque">
            @foreach ($chequesCob as $cheque)
                @php
                    $negociableCob = \App\Support\Caja\ChequePropioInstrumentoSupport::negociable(
                        (string) ($cheque->negociable ?? ''),
                        'N'
                    );
                @endphp
                <tr class="item-cobranza-cheque">
                    <td>
                        <input type="date" class="fechapago form-control" name="fechapagos[]" value="{{ $cheque->fechapago ?? '' }}">
                    </td>
                    <td>
                        <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                            <input type="hidden" class="banco_id" name="banco_ids[]" value="{{ $cheque->banco_id ?? '' }}" >
                            <input type="hidden" class="banco_id_previo" name="banco_id_previos[]" value="{{ $cheque->banco_id ?? '' }}" >
                            <button type="button" title="Consulta Bancos (F1)" class="btn-accion-tabla consultabanco tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" class="codigobanco form-control" name="codigobancos[]" value="{{ $cheque->bancos->codigo ?? '' }}"
                                   placeholder="C&oacute;d." style="width:5.5rem;flex-shrink:0;" autocomplete="off">
                            <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{ $cheque->bancos->codigo ?? '' }}" >
                            <input type="text" class="nombrebanco form-control text-truncate" name="nombrebancos[]" value="{{ $cheque->bancos->nombre ?? '' }}" readonly
                                   style="min-width:0;flex:1 1 auto;">
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-nowrap align-items-center" style="gap:2px;">
                            <input type="text" class="numerocheque form-control" name="numerocheques[]" value="{{ $cheque->numerocheque ?? '' }}"
                                   style="min-width:0;flex:1 1 auto;" autocomplete="off">
                            <select name="negociables[]" class="negociable form-control form-control-sm flex-shrink-0"
                                    style="width:3.1rem;padding:0.15rem 0;font-size:0.7rem;line-height:1.2;"
                                    title="F&iacute;sico / e-cheq (Anita negociable)">
                                <option value="N" @selected($negociableCob === 'N')>F&iacute;s</option>
                                <option value="E" @selected($negociableCob === 'E')>eCh</option>
                            </select>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="sucursalpago form-control" name="sucursalpagos[]" value="{{ $cheque->sucursalpago ?? '' }}">
                    </td>
                    <td>
                        <input type="text" class="cuentalibradora form-control" name="cuentalibradoras[]" value="{{ $cheque->cuentalibradora ?? '' }}">
                    </td>
                    <td>
                        <select name="monedacheque_ids[]" class="monedacheque_id form-control required" required data-fouc>
                            <option value="">--</option>
                            @foreach($moneda_query as $value)
                                <option value="{{ $value->id }}" @selected((int) $value->id === (int) ($cheque->moneda_id ?? 0))>{{ $value->abreviatura }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" name="montocheques[]" class="form-control montocheque text-right" min="0" value="{{ $cheque->monto ?? '' }}">
                    </td>
                    <td>
                        <input type="number" name="cotizacioncheques[]" class="form-control cotizacioncheque text-right" value="{{ $cheque->cotizacion ?? '0' }}">
                        <input type="hidden" name="cheque_ids[]" class="form-control cheque_id" value="{{ $cheque->id ?? '0' }}">
                    </td>
                    <td class="text-nowrap">
                        <div class="d-inline-flex flex-nowrap align-items-center" style="gap:2px;">
                            @if (can('editar-cheque', false) && ! empty($cheque->id))
                                <a href="{{ route('editar_cheque', ['id' => $cheque->id, 'origen' => 'cobranza']) }}" class="btn-accion-tabla tooltipsC" title="Editar el cheque" target="_blank" rel="noopener">
                                    <i class="fa fa-edit"></i>
                                </a>
                            @endif
                            @php
                                $chequeIdNd = (int) ($cheque->id ?? 0);
                                $puedeRechazarNdFila = ($puede_nd_cheque ?? false)
                                    && $chequeIdNd > 0
                                    && ($cheque->origen ?? 'R') === 'R'
                                    && ! in_array((string) ($cheque->estado ?? ''), ['R', 'A'], true)
                                    && empty($cheque->venta_nd_id)
                                    && ! empty($cheque->cliente_id);
                            @endphp
                            @if ($puedeRechazarNdFila)
                                <button type="button"
                                        class="btn-accion-tabla tooltipsC btn-rechazo-nd-cheque"
                                        title="Rechazar y emitir ND"
                                        data-cheque-id="{{ $chequeIdNd }}">
                                    <i class="fa fa-ban text-danger"></i>
                                </button>
                            @endif
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cobranza_cheque tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @include('caja.cobranza.template3')
        <div class="mb-2">
            <button type="button" id="agrega_renglon_cheque" class="btn btn-outline-primary btn-sm">+ Agrega rengl&oacute;n</button>
        </div>
        <div class="form-group row totales-por-moneda-cheque"></div>
        <div class="form-group row totales-cobranza"></div>
    </div>
</div>
@include('includes.caja.modalconsultabanco')
@if ($puede_nd_cheque ?? false)
    @include('caja.cheque.modal_rechazo_nd')
@endif
