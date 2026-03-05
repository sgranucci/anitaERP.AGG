<div class="card form2" style="display: none">    
    <h3>Cuentas</h3>
    <div class="card-body">
        <table class="table" id="cuenta-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Código</th>
                    <th style="width: 18%;">Descripción</th>
                    <th style="width: 7%;">Moneda</th>
                    <th style="width: 15%;">Monto</th>
                    <th style="width: 12%;">Cotización</th>
                    <th>Observación</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-cuenta-table">
            @if ($data->caja_movimientos[0]->caja_movimiento_cuentacajas ?? '') 
                @foreach (old('cuenta', $data->caja_movimientos[0]->caja_movimiento_cuentacajas->count() ? $data->caja_movimientos[0]->caja_movimiento_cuentacajas : ['']) as $cuenta)
                    <tr class="item-cuenta">
                        <td>
                            <div class="form-group row" id="cuenta">
                                <input type="hidden" name="cuentacaja[]" class="form-control iicuenta" readonly value="{{ $loop->index+1 }}" />
                                <input type="hidden" class="cuentacaja_id" name="cuentacaja_ids[]" value="{{$cuenta->cuentacaja_id ?? ''}}" >
                                <input type="hidden" class="cuentacaja_id_previa" name="cuentacaja_id_previa[]" value="{{$cuenta->cuentacaja_id ?? ''}}" >
                                <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuentacaja tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigo form-control" name="codigos[]" value="{{$cuenta->cuentacajas->codigo ?? ''}}" >
                                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{$cuenta->cuentacajas->codigo ?? ''}}" >
                            </div>
                        </td>							
                        <td>
                            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombre form-control" name="nombres[]" value="{{$cuenta->cuentacajas->nombre ?? ''}}" readonly>
                        </td>
                        <td>
                            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control required" required readonly data-fouc>
                                <option value="">-- Seleccionar --</option>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) old('moneda_ids[]', $cuenta->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
                                    @endif
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" name="montos[]" class="form-control monto" value="{{old('montos[]', abs($cuenta->monto) ?? '')}}">
                        </td>
                        <td>
                            <input type="number" name="cotizaciones[]" class="form-control cotizacion" value="{{old('cotizaciones[]', $cuenta->cotizacion ?? '0')}}">
                        </td>
                        <td>
                            <input type="text" name="observaciones[]" class="form-control observacion" value="{{old('observaciones[]', $cuenta->observacion ?? '')}}">
                        </td>
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
        @include('caja.cobranza.template2')
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
                    <button id="agrega_renglon_cuenta" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
        </div>
        <div class="form-group row totales-por-moneda">
        </div>
        <div class="form-group row totales-cobranza">
        </div>           
    </div>
</div>

    