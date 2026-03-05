<template id="template-renglon">
	<tr class="item-entrega">
    	<td>
       		<input type="text" name="entregas[]" class="form-control iientrega" readonly value="1" />
       	</td>
       	<td>
       		<input type="text" name="nombres[]" class="form-control" value="" />
       	</td>
       	<td>
       		<input type="text" name="domicilios[]" class="form-control" value="" />
       	</td>
		<td>
    		<select name="provincias_id[]" id="provincias_id" data-placeholder="Provincia" class="form-control provincias required" data-fouc>
    			<option value="">-- Seleccionar --</option>
    				@foreach($provincia_query as $key => $value)
    					<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
    				@endforeach
    		</select>
    		<input type="hidden" id="desc_provincia" name="desc_provincia" value="{{old('desc_provincia' ?? '')}}" >
    	</td>
    	<td>
    		<select name="localidades_id[]" data-placeholder="Localidad" class="form-control localidades" data-fouc>
    			@if($entrega->localidad_id ?? '')
        			<option value="{{old('localidades_id', $entrega->localidad_id)}}" selected>{{$entrega->desc_localidades}}</option>
    			@endif
    		</select>
        	<input type="hidden" class="localidad_id_previas" name="localidad_id_previas[]" value="" >
			<input type="hidden" class="desc_localidades" name="desc_localidades[]" value="" >
    	</td>
    	<td>
    		<div class="form-group">
    			<input type="text" name="codigospostales[]" value="" class="form-control codigospostales" placeholder="Codigo Postal">
    		</div>
    	</td>
    	<td>
    		<select name="transportes_id[]" data-placeholder="Transpore" class="col-lg-10 form-control" data-fouc>
    			<option value="">-- Seleccionar Transporte --</option>
    			@foreach($transporte_query as $key => $value)
    				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
    			@endforeach
    		</select>
    	</td>
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
