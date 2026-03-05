<div class="card form2" style="display: none">
    <h3>Gastos anteriores</h3>
    <div class="card-body">
        <input type="hidden" name="empresa_id" id="empresa_id" class="form-control" value="1" />
        <table class="table" id="rendicionreceptivo-gastoanterior-table">
            <thead>
                <tr>
                    <th style="width: 8%;">ID gasto</th>
                    <th style="width: 30%;">Concepto del gasto</th>
                    <th style="width: 12%;">Cuenta de caja</th>
                    <th style="width: 20%;">Descripción</th>
                    <th style="width: 5%;">Moneda</th>
                    <th style="width: 15%;">Monto</th>
                    <th style="width: 20%;">Cotización</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-rendicionreceptivo-gastoanterior-table" class="container-gastoanterior">
            @if ($data->rendicionreceptivo_caja_movimientos ?? '') 
                @foreach (old('gastoanterior', $data->rendicionreceptivo_caja_movimientos->count() ? $data->rendicionreceptivo_caja_movimientos : ['']) as $gastoanterior)
                    @foreach($gastoanterior->caja_movimientos->caja_movimiento_cuentacajas as $gastoanteriorcuentacaja)
                        <tr class="item-rendicionreceptivo-gastoanterior">
                            <td>
                                <input type="text" class="idgastoanterior form-control" name="idgastoanteriores[]" value="{{$gastoanterior->caja_movimiento_id ?? ''}}" readonly>
                            </td>
                            <td>
                                <input type="text" class="nombreconceptogastoanterior form-control" name="nombreconceptogastoanteriores[]" value="{{$gastoanterior->caja_movimientos->conceptogastos->nombre ?? ''}}" readonly>
                            </td>
                            <td>
                                <input type="text" class="codigocuentacajagastoanterior form-control" name="codigocuentacajagastoanteriores[]" value="{{$gastoanteriorcuentacaja->cuentacajas->codigo ?? ''}}" readonly>
                            </td>							
                            <td>
                                <input type="text" class="nombrecuentacajagastoanterior form-control" name="nombrecuentacajagastoanteriores[]" value="{{$gastoanteriorcuentacaja->cuentacajas->nombre ?? ''}}" readonly>
                            </td>
                            <td>
                                <input type="text" class="abreviaturamonedagastoanterior form-control" name="abreviaturamonedagastoanteriores[]" value="{{$gastoanteriorcuentacaja->monedas->abreviatura ?? ''}}" readonly>
                                <input type="hidden" class="monedagastoanterior_id form-control" name="monedagastoanterior_ids[]" value="{{$gastoanteriorcuentacaja->monedas->id ?? ''}}" readonly>
                            </td>
                            <td>
                                <input type="number" name="montogastoanteriores[]" class="form-control montogastoanterior" min="0" value="{{old('montos[]', abs($gastoanteriorcuentacaja->monto) ?? '')}}">
                            </td>				
                            <td>
                                <input type="number" name="cotizaciongastoanteriores[]" class="form-control cotizaciongastoanterior" value="{{old('cotizaciones[]', $gastoanteriorcuentacaja->cotizacion ?? '0')}}">
                            </td>		
                            <td>
                                <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_rendicionreceptivo_gastoanterior tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach                        
                @endforeach
            @endif
            </tbody>
        </table>
        <div class="form-group row totales-por-moneda-gastoanterior">
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
@include('includes.caja.modalconsultacuentacaja')
