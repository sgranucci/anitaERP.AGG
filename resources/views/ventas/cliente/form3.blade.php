<div class="card form3" style="display: none">
    <div class="card-body">
		<div class="form-group row">
			<label for="lugarentrega" class="col-lg-3 col-form-label">Lugar de Entrega</label>
			<div class="col-lg-6">
				<input type="text" name="lugarentrega" id="lugarentrega" class="form-control" value="{{old('lugarentrega', $data->lugarentrega ?? '')}}"/>
			</div>
		</div>
    	<table class="table" id="cuotas-table">
    		<thead>
    			<tr>
    				<th style="width: 7%;">Cod.</th>
    				<th style="width: 25%;">Nombre</th>
    				<th style="width: 20%;">Domicilio</th>
    				<th style="width: 15%;">Provincia</th>
    				<th style="width: 15%;">Localidad</th>
    				<th>Cod.Postal</th>
    				<th style="width: 15%;">Transporte</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla">
		 		@if ($data->cliente_entregas ?? '') 
					@foreach (old('entregas', $data->cliente_entregas->count() ? $data->cliente_entregas : ['']) as $entrega)
            			<tr class="item-entrega">
                			<td>
                				<input type="text" name="entregas[]" class="form-control iientrega" readonly value="{{ $loop->index+1 }}" />
                			</td>
                			<td>
                				<input type="text" name="nombres[]" class="form-control"
                					value="{{ (old('nombres.' . $loop->index) ?? optional($entrega)->nombre) ?? '' }}" />
                			</td>
                			<td>
                				<input type="text" name="domicilios[]" class="form-control"
                					value="{{ (old('domicilios.' . $loop->index) ?? optional($entrega)->domicilio) ?? '' }}" />
                			</td>
							<td>
        						<select name="provincias_id[]" id="provincias_id" data-placeholder="Provincia" class="form-control provincias" data-fouc>
        							<option value="">-- Seleccionar --</option>
        							@foreach($provincia_query as $key => $value)
        								@if( (int) $value->id == (int) old('provincias_id', $entrega->provincia_id ?? ''))
        									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        								@else
        									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        								@endif
        							@endforeach
        						</select>
        						<input type="hidden" id="desc_provincias" name="desc_provincias[]" value="{{old('desc_provincia', $entrega->desc_provincia ?? '')}}" >
        					</td>
        					<td>
        						<select name="localidades_id[]" data-placeholder="Localidad" class="form-control localidades" data-fouc>
        							@if($entrega->localidad_id ?? '')
										@if($entrega->localidad_id == "")
        									<option selected></option>
        								@else
        									<option value="{{old('localidades_id', $entrega->localidad_id)}}" selected>{{$entrega->desc_localidades}}</option>
										@endif
        							@endif
        						</select>
        						<input type="hidden" class="localidad_id_previas" name="localidad_id_previas[]" value="{{old('localidad_id_previas.'. $loop->index, $entrega->localidad_id ?? '')}}" >
								<input type="hidden" class="desc_localidades" name="desc_localidades[]" value="{{old('desc_localidades.' . $loop->index, $entrega->desc_localidades ?? '')}}" >
                			</td>
                			<td>
        						<div class="form-group">
        							<input type="text" name="codigospostales[]" value="{{old('codigospostales.' . $loop->index, $entrega->codigopostal ?? '')}}" class="form-control codigospostales" placeholder="C&oacute;digo Postal">
        						</div>
                			</td>
                			<td>
        						<select name="transportes_id[]" id="transportes_id" data-placeholder="Transpore" class="col-lg-10 form-control" data-fouc>
        							<option value="">-- Seleccionar Transporte --</option>
        							@foreach($transporte_query as $key => $value)
        								@if( (int) $value->id == (int) old('transporte_id', $entrega->transporte_id ?? ''))
        									<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
        								@else
        									<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        								@endif
        							@endforeach
        						</select>
                			</td>
                			<td>
								<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
                            		<i class="fa fa-times-circle text-danger"></i>
								</button>
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('ventas.cliente.template3')
        <div class="row">
        	<div class="col-md-12">
        		<button id="agrega_renglon" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
        	</div>
        </div>
    </div>
</div>
