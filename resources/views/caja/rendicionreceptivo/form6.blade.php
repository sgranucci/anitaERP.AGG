<div class="card form6" style="display: none">
    <h3>Adelantos</h3>
    <div class="card-body">
        <table class="table" id="rendicionreceptivo-adelanto-table">
            <thead>
                <tr>
                    <th style="width: 8%;">ID Adelanto</th>
                    <th style="width: 30%;">Concepto</th>
                    <th style="width: 12%;">Cuenta de caja</th>
                    <th style="width: 20%;">Descripción</th>
                    <th style="width: 5%;">Moneda</th>
                    <th style="width: 15%;">Monto</th>
                    <th style="width: 20%;">Cotización</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-rendicionreceptivo-adelanto-table" class="container-adelanto">
            @if (count($data->rendicionreceptivo_adelantos) > 0) 
                @foreach (old('adelanto', $data->rendicionreceptivo_adelantos->count() ? $data->rendicionreceptivo_adelantos : ['']) as $adelanto)
                    @foreach($adelanto->caja_movimientos->caja_movimiento_cuentacajas as $adelantocuentacaja)
                        <tr class="item-rendicionreceptivo-adelanto">
                            <td>
                                <input type="text" class="idadelanto form-control" name="idadelantos[]" value="{{$adelanto->caja_movimiento_id ?? ''}}" readonly>
                            </td>
                            <td>
                                <input type="text" class="nombreconceptoadelanto form-control" name="nombreconceptoadelantos[]" value="{{$adelanto->caja_movimientos->conceptogastos->nombre ?? ''}}" readonly>
                            </td>
                            <td>
                                <input type="text" class="codigocuentacajaadelanto form-control" name="codigocuentacajaadelantos[]" value="{{$adelantocuentacaja->cuentacajas->codigo ?? ''}}" readonly>
                            </td>							
                            <td>
                                <input type="text" class="nombrecuentacajaadelanto form-control" name="nombrecuentacajaadelantos[]" value="{{$adelantocuentacaja->cuentacajas->nombre ?? ''}}" readonly>
                            </td>
                            <td>
                                <input type="text" class="abreviaturamonedaadelanto form-control" name="abreviaturamonedaadelantos[]" value="{{$adelantocuentacaja->monedas->abreviatura ?? ''}}" readonly>
                                <input type="hidden" class="monedaadelanto_id form-control" name="monedaadelanto_ids[]" value="{{$adelantocuentacaja->monedas->id ?? ''}}" readonly>
                            </td>
                            <td>
                                <input type="number" name="montoadelantoes[]" class="form-control montoadelanto" min="0" value="{{old('montos[]', abs($adelantocuentacaja->monto) ?? '')}}">
                            </td>				
                            <td>
                                <input type="number" name="cotizacionadelantoes[]" class="form-control cotizacionadelanto" value="{{old('cotizaciones[]', $adelantocuentacaja->cotizacion ?? '0')}}">
                            </td>		
                            <td>
                                <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_rendicionreceptivo_adelanto tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach                        
                @endforeach
            @endif
            </tbody>
        </table>
        <div class="form-group row totales-por-moneda-adelanto">
        </div>
    </div>
</div>
