<div class="card form8" style="display: none">
    <div class="card-body">
    	<table class="table" id="cm05-table">
    		<thead>
    			<tr>
    				<th style="width: 12%;">Provincia</th>
					<th style="width: 15%;">Nombre Provincia</th>
    				<th style="width: 35%;">Tipo Percepción</th>
					<th>Coeficiente</th>
					<th style="width: 15%;">Fecha vigencia</th>
					<th style="width: 10%;">No Ret.</th>
					<th>Desde Fecha</th>
					<th>Hasta Fecha</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla-cm05">
		 		@if ($data->cliente_cm05s ?? '') 
					@foreach (old('cliente_cm05', $data->cliente_cm05s->count() ? $data->cliente_cm05s : ['']) as $cm05)
            			<tr class="item-cm05">
                			<td>
                				<input type="hidden" name="cliente_cm05[]" class="form-control iicm05" readonly value="{{ $loop->index+1 }}" />
                                <div class="form-group row" id="provincia">
                                    <input type="hidden" class="provincia_id" name="provincia_ids[]" value="{{$cm05->provincia_id ?? ''}}" >
                                    <input type="hidden" class="provincia_id_previa" name="provincia_id_previa[]" value="{{$cm05->provincia_id ?? ''}}" >
                                    <button type="button" title="Consulta provincias" style="padding:1;" class="btn-accion-tabla consultaprovincia tooltipsC">
                                            <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" style="WIDTH: 80px;HEIGHT: 38px" class="codigoprovincia form-control" name="codigoprovincias[]" value="{{$cm05->provincias->codigo ?? ''}}" >
                                    <input type="hidden" class="codigo_previo_provincia" name="codigo_previo_provincias[]" value="{{$cm05->provincias->codigo ?? ''}}" >
                                </div>
                            </td>							
                            <td>
                                <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombreprovincia form-control" name="nombreprovincias[]" value="{{$cm05->provincias->nombre ?? ''}}" readonly>
                            </td>
							<td>
								<select name="tipopercepciones[]" class="form-control tipopercepcion requerido" required>
									<option value="">-- Elija tipo de percepción --</option>
									@foreach ($tipopercepcion_enum as $value => $tipo)
										<option value="{{ $value }}"
										@if (old('tipopercepciones.' . $loop->parent->index, optional($cm05)->tipopercepcion) == $value) selected @endif
									>{{ $tipo }}</option>
									@endforeach
								</select>
							</td>							
							<td>
                                <input type="number" min="0" max="100" class="coeficiente form-control" name="coeficientes[]" value="{{$cm05->coeficiente ?? ''}}">
                            </td>
							<td>
								<input type="date" class="fechavigencia form-control" name="fechavigencias[]" value="{{$cm05->fechavigencia ?? date('d-m-Y')}}">
							</td>
							<td>
								<select name="certificadonoretenciones[]" class="form-control certificadonoretencion requerido" required>
									@foreach ($certificadonoretencion_enum as $value => $certificado)
										<option value="{{ $value }}"
										@if (old('certificadonoretenciones.' . $loop->parent->index, optional($cm05)->certificadonoretencion) == $value) selected @endif
									>{{ $certificado }}</option>
									@endforeach
								</select>
							</td>									
							<td>
								<input type="date" class="desdefechanoretencion form-control" name="desdefechanoretenciones[]" value="{{$cm05->desdefechanoretencion ?? date('d-m-Y')}}">
							</td>							
							<td>
								<input type="date" class="hastafechanoretencion form-control" name="hastafechanoretenciones[]" value="{{$cm05->hastafechanoretencion ?? date('d-m-Y')}}">
							</td>							
							<input type="hidden" name="creousuario_cm05_ids[]" class="form-control creousuario_cm05_id" value="{{ $cm05->creousuario_id ?? auth()->id()}}"/>
                			<td>
								<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cm05 tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('ventas.cliente.template8')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon_cm05" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
@include('includes.configuracion.modalconsultaprovincia')
