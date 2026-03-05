<div class="form-group row">
	<label for="desdearticulo" class="col-lg-3 col-form-label ">Art&iacute;culo</label>
	<select name="articulo_id" id="articulo_id" data-placeholder="articulo" class="col-lg-4 form-control " data-fouc>
		<option value="">-- Seleccionar art&iacute;culo --</option>
		@foreach($articulo_query as $key => $value)
			@if( (int) $value->id == '0')
				<option value="{{ $value->id }}" selected="select">{{ $value->descripcion }}</option>    
			@else
			<option value="{{ $value->id }}">{{ $value->descripcion }}</option>    
			@endif
		@endforeach
	</select>
</div>
