<div class="card form3" style="display: none">
    <h3>Vouchers</h3>
    <div class="card-body">
        <table class="table" id="rendicionreceptivo-voucher-table">
            <thead>
                <tr>
                    <th style="width: 8%;">ID Voucher</th>
                    <th style="width: 10%;">Fecha</th>
                    <th style="width: 12%;">Cuenta de caja</th>
                    <th style="width: 30%;">Descripción</th>
                    <th style="width: 5%;">Moneda</th>
                    <th style="width: 20%;">Monto</th>
                    <th style="width: 20%;">Cotización</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-rendicionreceptivo-voucher-table" class="container-voucher">
            @if ($data->rendicionreceptivo_vouchers ?? '') 
                @foreach (old('voucher', $data->rendicionreceptivo_vouchers->count() ? $data->rendicionreceptivo_vouchers : ['']) as $voucher)
                @foreach ($voucher->vouchers->voucher_formapagos as $unvoucherformapago)
                    <tr class="item-rendicionreceptivo-voucher">
                        <td>
                            <input type="text" class="idvoucher form-control" name="idvouchers[]" value="{{$voucher->voucher_id ?? ''}}" readonly>
                        </td>
                        <td>
                            <input type="date" class="fechavoucher form-control" name="fechavouchers[]" value="{{$voucher->vouchers->fecha ?? ''}}" readonly>
                        </td>
                        <td>
                            <input type="text" class="codigocuentacajavoucher form-control" name="codigocuentacajavoucheres[]" value="{{$unvoucherformapago->cuentacajas->codigo ?? ''}}" readonly>
                            <input type="hidden" class="cuentacajavoucher_id form-control" name="cuentacajavoucher_ids[]" value="{{$unvoucherformapago->cuentacajas->id ?? ''}}" readonly>
                        </td>							
                        <td>
                            <input type="text" class="nombrecuentacajavoucher form-control" name="nombrecuentacajavoucheres[]" value="{{$unvoucherformapago->cuentacajas->nombre ?? ''}}" readonly>
                        </td>
                        <td>
                            <input type="text" class="nombremonedavoucher form-control" name="nombremonedavoucheres[]" value="{{$unvoucherformapago->monedas->abreviatura ?? ''}}" readonly>
                            <input type="hidden" class="monedavoucher_id form-control" name="monedavoucher_ids[]" value="{{$unvoucherformapago->moneda_id ?? ''}}">
                        </td>
                        <td>
                            <input type="number" name="montovoucheres[]" class="form-control montovoucher" min="0" value="{{old('montos[]', $unvoucherformapago->monto ?? '')}}">
                        </td>				
                        <td>
                            <input type="number" name="cotizacionvoucheres[]" class="form-control cotizacionvoucher" value="{{old('cotizaciones[]', $unvoucherformapago->cotizacion ?? '0')}}">
                        </td>		
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_rendicionreceptivo_voucher tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
                @endforeach
            @endif
            </tbody>
        </table>
        <div class="form-group row totales-por-moneda-voucher">
        </div>
    </div>
</div>
