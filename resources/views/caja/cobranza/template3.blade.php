<template id="template-renglon-cheque">
    <tr class="item-cobranza-cheque">
        <td>
            <input type="date" class="fechapago form-control" name="fechapagos[]" value="">
        </td>
        <td>
            <div class="form-group row" id="banco">
                <input type="hidden" class="banco_id" name="banco_ids[]" value="" >
                <input type="hidden" class="banco_id_previo" name="banco_id_previos[]" value="" >
                <button type="button" title="Consulta Bancos" style="padding:1;" class="btn-accion-tabla consultabanco tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigobanco form-control" name="codigos[]" value="" >
                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="" >
                <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrebanco form-control" name="nombrebancos[]" value="" readonly>
            </div>                            
        </td>
        <td>
            <input type="text" class="numerocheque form-control" name="numerocheques[]" value="">
        </td>							
        <td>
            <input type="text" class="sucursalpago form-control" name="sucursalpagos[]" value="">
        </td>
        <td>
            <input type="text" class="cuentalibradora form-control" name="cuentalibradoras[]" value="">
        </td>  
        <td>
            <select name="monedacheque_ids[]" data-placeholder="Moneda" class="monedacheque_id form-control required" required data-fouc>
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
            <input type="number" name="montocheques[]" class="form-control montocheque" min="0" value="">
        </td>				
        <td>
            <input type="number" name="cotizacioncheques[]" class="form-control cotizacioncheque" value="0">
            <input type="hidden" name="cheque_ids[]" class="form-control cheque_id" value="">
        </td>		
        <td>
            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cobranza_cheque tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>