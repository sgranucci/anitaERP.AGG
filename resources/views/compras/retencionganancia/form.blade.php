<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $retencionganancia->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="formacalculo" class="col-lg-3 col-form-label requerido">Forma de cálculo</label>
	<select id="formacalculo" name="formacalculo" class="col-lg-4 form-control" required>
    	<option value="">-- Elija forma de cálculo --</option>
       	@foreach($formacalculo_enum as $formacalculo)
			@if ($formacalculo['valor'] == old('formacalculo',$retencionganancia->formacalculo??''))
       			<option value="{{ $formacalculo['valor'] }}" selected>{{ $formacalculo['nombre'] }}</option>    
			@else
			    <option value="{{ $formacalculo['valor'] }}">{{ $formacalculo['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="porcentajeinscripto" class="col-lg-3 col-form-label requerido">Porcentaje inscripto</label>
    <div class="col-lg-3">
    <input type="number" name="porcentajeinscripto" id="porcentajeinscripto" class="form-control" value="{{old('porcentajeinscripto', $retencionganancia->porcentajeinscripto ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="porcentajenoinscripto" class="col-lg-3 col-form-label requerido">Porcentaje no inscripto</label>
    <div class="col-lg-3">
    <input type="number" name="porcentajenoinscripto" id="porcentajenoinscripto" class="form-control" value="{{old('porcentajenoinscripto', $retencionganancia->porcentajenoinscripto ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="excedente" class="col-lg-3 col-form-label requerido">Excedente</label>
    <div class="col-lg-3">
    <input type="number" name="montoexcedente" id="montoexcedente" class="form-control" value="{{old('montoexcedente', $retencionganancia->montoexcedente ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="regimen" class="col-lg-3 col-form-label requerido">Código de régimen</label>
    <div class="col-lg-3">
    <input type="number" name="regimen" id="regimen" class="form-control" value="{{old('regimen', $retencionganancia->regimen ?? '')}}">
    </div>
</div>
<div class="form-group row" id="divbaseimponible">
    <label for="baseimponible" class="col-lg-3 col-form-label requerido">Base imponible</label>
    <div class="col-lg-4">
    <input type="number" name="baseimponible" id="baseimponible" class="form-control" value="{{old('baseimponible', $retencionganancia->baseimponible ?? '')}}">
    </div>
</div>
<div class="form-group row" id="divcantidadperiodoacumula">
    <label for="cantidadperiodoacumula" class="col-lg-3 col-form-label requerido">Cantidad de períodos que acumula</label>
    <div class="col-lg-2">
    <input type="number" name="cantidadperiodoacumula" id="cantidadperiodoacumula" class="form-control" value="{{old('cantidadperiodoacumula', $retencionganancia->cantidadperiodoacumula ?? '')}}">
    </div>
</div>
<div class="form-group row" id="divvalorunitario">
    <label for="valorunitario" class="col-lg-3 col-form-label requerido">Valor unitario</label>
    <div class="col-lg-2">
    <input type="number" name="valorunitario" id="valorunitario" class="form-control" value="{{old('valorunitario', $retencionganancia->valorunitario ?? '0')}}">
    </div>
</div>
<div class="form-group row">
    <label for="minimoretencion" class="col-lg-3 col-form-label requerido">Mínimo retención</label>
    <div class="col-lg-3">
    <input type="number" name="minimoretencion" id="minimoretencion" class="form-control" value="{{old('minimoretencion', $retencionganancia->minimoretencion ?? '0')}}">
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-1">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $retencionganancia->codigo ?? '0')}}" readonly>
    </div>
</div>
<div class="card">
    <div class="card-header">
    	Escala
    </div>

    <div class="card-body">
    	<table class="table" id="cuotas-table">
    		<thead>
    			<tr>
					<th>Renglón</th>
    				<th>Desde monto</th>
    				<th>Hasta monto</th>
    				<th>Retiene</th>
    				<th>Mas %</th>
    				<th>Sobre excedente</th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($retencionganancia->retencionganancia_escalas ?? '') 
					@foreach (old('cuotas', $retencionganancia->retencionganancia_escalas->count() ? $retencionganancia->retencionganancia_escalas : ['']) as $retencionganancia_escala)
            			<tr class="item-cuota">
							<td>
                				<input type="number" name="cuotas[]" class="form-control iicuota" readonly value="{{ $loop->index+1 }}" />
                			</td>
                			<td>
                				<input type="number" name="desdemontos[]" class="form-control desdemonto"
                					value="{{ (old('desdemontos.' . $loop->index) ?? optional($retencionganancia_escala)->desdemonto) ?? '0' }}" />
                			</td>
                			<td>
                				<input type="number" name="hastamontos[]" class="form-control hastamonto"
                					value="{{ (old('hastamontos.' . $loop->index) ?? optional($retencionganancia_escala)->hastamonto) ?? '0' }}" />
                			</td>
                			<td>
                				<input type="number" name="montoretenciones[]" class="form-control retencion"
                					value="{{ (old('montoretenciones.' . $loop->index) ?? optional($retencionganancia_escala)->montoretencion) ?? '0' }}" />
                			</td>							
							<td>
                				<input type="number" name="porcentajeretenciones[]" class="form-control"
                					value="{{ (old('porcentajeretenciones.' . $loop->index) ?? optional($retencionganancia_escala)->porcentajeretencion) ?? '0' }}" />
                			</td>
                			<td>
                				<input type="number" name="excedentes[]" class="form-control"
                					value="{{ (old('excedentes.' . $loop->index) ?? optional($retencionganancia_escala)->excedente) ?? '0' }}" />
                			</td>
                			<td>
								<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@else
            		<tr class="item-cuota">
						<td>
                			<input type="number" id="icuota" name="cuotas[]" class="form-control iicuota" readonly value="1" />
                		</td>
                		<td>
                			<input type="number" id="desdemonto" name="desdemontos[]" class="form-control desdemonto" value="0"/>
                		</td>
						<td>
                			<input type="number" id="hastamonto" name="hastamontos[]" class="form-control hastamonto" value="0" />
                		</td>
						<td>
                			<input type="number" id="montoretencion" name="montoretenciones[]" class="form-control montoretencion" value="0" />
                		</td>
						<td>
                			<input type="number" id="porcentajeretencion" name="porcentajeretenciones[]" class="form-control porcentajeretencion" value="0" />
                		</td>						
						<td>
                			<input type="number" id="excedente" name="excedentes[]" class="form-control excedente" value="0" />
                		</td>						
						<td>
							<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
                            	<i class="fa fa-times-circle text-danger"></i>
							</button>
                		</td>
           			</tr>
				@endif
       		</tbody>
       	</table>
		@include('compras.retencionganancia.template')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
