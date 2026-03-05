<div class="card form2" style="display: none">
    <h3>Reservas</h3>
    <div class="card-body">
        <table class="table" id="voucher-reserva-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Reserva</th>
                    <th>Pasajero</th=>
                    <th>Fecha arribo</th=>
                    <th>Fecha partida</th>
                    <th style="width: 10%;">Pax</th>
                    <th style="width: 10%;">Free</th>
                    <th style="width: 10%;">Incluido</th>
                    <th style="width: 10%;">Opcional</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-voucher-reserva-table" class="container-reserva">
            @if ($data->voucher_reservas ?? '') 
                @foreach (old('reserva', $data->voucher_reservas->count() ? $data->voucher_reservas : ['']) as $reserva)
                    <tr class="item-voucher-reserva">
                        <td>
                            <div class="form-group row" id="reserva">
                                <input type="hidden" name="reserva[]" class="form-control iireserva" readonly value="{{ $loop->index+1 }}" />
                                <input type="hidden" class="reserva_id" name="reserva_ids[]" value="{{$reserva->reserva_id ?? ''}}" >
                                <input type="hidden" class="reserva_id_previa" name="reserva_id_previa[]" value="{{$reserva->reserva_id ?? ''}}" >
                                <button type="button" title="Consulta reservas" style="padding:1;" class="btn-accion-tabla consultareserva tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigoreserva form-control" name="codigoreservas[]" value="{{$reserva->reserva_id ?? ''}}" >
                                <input type="hidden" class="codigo_previo_reserva" name="codigo_previo_reservas[]" value="{{$reserva->reserva_id ?? ''}}" >
                                <input type="hidden" class="carga_reserva_manual" name="carga_reserva_manuales[]" value="{{old('carga_reserva_manuales', 0 ?? '')}}" >
                            </div>
                        </td>							
                        <td>
                            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrepasajero form-control" name="nombrepasajeros[]" value="{{$reserva->nombrepasajero ?? ''}}" readonly>
                            <input type="hidden" name="pasajero_ids[]" class="form-control pasajero_id" value="{{old('pasajero_ids', $reserva->pasajero_id ?? '')}}">
                        </td>
                        <td>
                            <input type="date" name="fechaarribos[]" class="form-control fechaarribo" value="{{old('fechaarribos[]', $reserva->fechaarribo ?? '')}}">
                        </td>
                        <td>
                            <input type="date" name="fechapartidas[]" class="form-control fechapartida" value="{{old('fechapartidas[]', $reserva->fechapartida ?? '')}}">
                        </td>
                        <td>
                            <input type="number" name="paxs[]" class="form-control pax" min="0" step="1" value="{{old('paxs[]', $reserva->pax ?? '0')}}">
                            <input type="hidden" name="limitepaxs[]" class="form-control limitepax" value="{{old('limitepaxs[]', $reserva->limitepax ?? '0')}}">
                        </td>
                        <td>
                            <input type="number" name="frees[]" class="form-control free" min="0" step="1" value="{{old('frees[]', $reserva->free ?? '0')}}">
                            <input type="hidden" name="limitefrees[]" class="form-control limitefree" value="{{old('limitefrees[]', $reserva->limitefree ?? '0')}}">
                        </td>
                        <td>
                            <input type="number" name="incluidos[]" class="form-control incluido" min="0" step="1" value="{{old('incluidos[]', $reserva->incluido ?? '0')}}">
                        </td>
                        <td>
                            <input type="number" name="opcionales[]" class="form-control opcional" min="0" step="1" value="{{old('opcionales[]', $reserva->opcional ?? '0')}}">
                        </td>
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_voucher_reserva tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
        @include('caja.voucher.templatereserva')
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
                    <button id="agrega_renglon_voucher_reserva" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
            <div class="form-group row">
                <label for="totalpaxvoucher" class="col-lg-3 col-form-label">Total pax</label>
                <input type="text" id="totalpaxvoucher" name="totalpaxvoucher" class="form-control col-lg-3" readonly value="" />
                <label for="totalfreevoucher" class="col-lg-3 col-form-label">Total free</label>
                <input type="text" id="totalfreevoucher" name="totalfreevoucher" class="form-control col-lg-3" readonly value="" />
                <label for="totalincluidovoucher" class="col-lg-3 col-form-label">Total incluidos</label>
                <input type="text" id="totalincluidovoucher" name="totalincluidovoucher" class="form-control col-lg-3" readonly value="" />
                <label for="totalopcionalvoucher" class="col-lg-3 col-form-label">Total opcionales</label>
                <input type="text" id="totalopcionalvoucher" name="totalopcionalvoucher" class="form-control col-lg-3" readonly value="" />
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

