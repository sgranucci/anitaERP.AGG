<div class="card form3" style="display: none">
    <h3>Cheques recibidos</h3>
    <div class="card-body">
        <table class="table" id="cobranza-cheque-table">
            <thead>
                <tr>
                    <th style="width: 8%;">Fecha del cheque</th>
                    <th style="width: 30%;">Banco</th>
                    <th style="width: 12%;">Nro. de Cheque</th>
                    <th style="width: 6%;">Sucursal</th>
                    <th style="width: 10%;">Cuenta</th>
                    <th style="width: 5%;">Moneda</th>
                    <th style="width: 15%;">Monto</th>
                    <th style="width: 20%;">Cotización</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-cobranza-cheque-table" class="container-cheque">
            @if (count($data->cheques ?? []) > 0) 
                @foreach (old('cheque', $data->cheques->count() ? $data->cheques : ['']) as $cheque)
                    <tr class="item-cobranza-cheque">
                        <td>
                            <input type="date" class="fechapago form-control" name="fechapagos[]" value="{{$cheque->fechapago ?? ''}}">
                        </td>
                        <td>
                            <div class="form-group row" id="banco">
                                <input type="hidden" class="banco_id" name="banco_ids[]" value="{{$cheque->banco_id ?? ''}}" >
                                <input type="hidden" class="banco_id_previo" name="banco_id_previos[]" value="{{$cheque->banco_id ?? ''}}" >
                                <button type="button" title="Consulta Bancos" style="padding:1;" class="btn-accion-tabla consultabanco tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigobanco form-control" name="codigobancos[]" value="{{$cheque->bancos->codigo ?? ''}}" >
                                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{$cheque->bancos->codigo ?? ''}}" >
                                <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrebanco form-control" name="nombrebancos[]" value="{{$cheque->bancos->nombre ?? ''}}" readonly>
                            </div>                            
                        </td>
                        <td>
                            <input type="text" class="numerocheque form-control" name="numerocheques[]" value="{{$cheque->numerocheque ?? ''}}">
                        </td>							
                        <td>
                            <input type="text" class="sucursalpago form-control" name="sucursalpagos[]" value="{{$cheque->sucursalpago ?? ''}}">
                        </td>
                        <td>
                            <input type="text" class="cuentalibradora form-control" name="cuentalibradoras[]" value="{{$cheque->cuentalibradora ?? ''}}">
                        </td>  
                        <td>
                            <select name="monedacheque_ids[]" data-placeholder="Moneda" class="monedacheque_id form-control required" required data-fouc>
                                <option value="">-- Seleccionar --</option>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) old('moneda_ids[]', $cheque->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                    @endif
                                @endforeach
                            </select>
                        </td>                                              
                        <td>
                            <input type="number" name="montocheques[]" class="form-control montocheque" min="0" value="{{old('montos[]', $cheque->monto ?? '')}}">
                        </td>				
                        <td>
                            <input type="number" name="cotizacioncheques[]" class="form-control cotizacioncheque" value="{{old('cotizaciones[]', $cheque->cotizacion ?? '0')}}">
                            <input type="hidden" name="cheque_ids[]" class="form-control cheque_id" value="{{old('cheque_ids[]', $cheque->id ?? '0')}}">
                        </td>		
                        <td>
                            @if (can('editar-cheque', false))
                                <a href="{{route('editar_cheque', ['id' => $cheque->id ?? 0, 'origen' => 'cobranza'])}}" class="btn-accion-tabla tooltipsC" title="Editar el cheque">
                                    <i class="fa fa-edit"></i>
                                </a>
                            @endif                            
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cobranza_cheque tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
        @include('caja.cobranza.template3')
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
                    <button id="agrega_renglon_cheque" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
        </div>
        <div class="form-group row totales-por-moneda-cheque">
        </div>
        <div class="form-group row totales-cobranza">
        </div>           
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
@include('includes.caja.modalconsultabanco')