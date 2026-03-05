<div class="card form4" style="display: none">
    <h3>Retenciones</h3>
    <div class="card-body">
        <table class="table" id="cobranza-retencion-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Retención</th>
                    <th style="width: 10%;">Comprobante</th>
                    <th style="width: 12%;">Tasa</th>
                    <th style="width: 5%;">Moneda</th>
                    <th style="width: 15%;">Monto</th>
                    <th style="width: 20%;">Cotización</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-cobranza-retencion-table" class="container-retencion">
            @if (count($data->cobranza_retenciones ?? []) > 0) 
                @foreach (old('retencion', $data->cobranza_retenciones->count() ? $data->cobranza_retenciones : ['']) as $retencion)
                    <tr class="item-cobranza-cheque">
                        <td>
                            <select name="retencion_cobranza_ids[]" data-placeholder="Retención" class="retencion_cobranza_id form-control required" required data-fouc>
                                @if (count($retencion_cobranza_query) > 1)
                                    <option value="">-- Seleccionar --</option>
                                @endif
                                @foreach($retencion_cobranza_query as $key => $value)
                                    @if( (int) $value->id == (int) old('retencion_cobranza_ids[]', $retencion->retencion_cobranza_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                    @endif
                                @endforeach
                            </select>                            
                        </td>
                        <td>
                            <input type="text" class="comprobanteretencion form-control" name="comprobante_retenciones[]" value="{{$retencion->comprobante ?? ''}}">
                        </td>							
                        <td>
                            <input type="text" class="tasaretencion form-control" name="tasa_retenciones[]" value="{{$retencion->tasa ?? ''}}">
                        </td>
                        <td>
                            <select name="moneda_retencion_ids[]" data-placeholder="Moneda" class="monedaretencion_id form-control required" required data-fouc>
                                @foreach($moneda_query as $key => $value)
                                    @if( (int) $value->id == (int) old('moneda_ids[]', $retencion->moneda_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                    @endif
                                @endforeach
                            </select>
                        </td>                                              
                        <td>
                            <input type="number" name="monto_retenciones[]" class="form-control montoretencion" min="0" value="{{old('montos[]', $retencion->monto ?? '')}}">
                        </td>				
                        <td>
                            <input type="number" name="cotizacion_retenciones[]" class="form-control cotizacionretencion" value="{{old('cotizacion_retenciones[]', $retencion->cotizacion ?? '0')}}">
                        </td>		
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cobranza_retencion tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
        @include('caja.cobranza.template4')
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
                    <button id="agrega_renglon_retencion" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
        </div>
        <div class="form-group row totales-por-moneda-retencion">
        </div>
        <div class="form-group row totales-cobranza">
        </div>   
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />