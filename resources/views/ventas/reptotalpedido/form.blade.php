<div class="form-group row">
	<label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
	<div class="col-lg-4">
		<input type="text" name="desdefecha" id="desdefecha" class="form-control" value="{{old('desdefecha', date('d-m-Y'))}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
	<div class="col-lg-4">
		<input type="text" name="hastafecha" id="hastafecha" class="form-control" value="{{old('desdefecha', date('d-m-Y'))}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="desdevendedor" class="col-lg-3 col-form-label requerido">Desde Vendedor</label>
   	<select name="desdevendedor_id" id="desdevendedor_id" data-placeholder="Vendedor" class="col-lg-4 form-control required" data-fouc>
   		<option value="">-- Seleccionar vendedor --</option>
       	@foreach($vendedor_query as $key => $value)
       		@if( (int) $value->id == '0')
       			<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
       		@else
       			<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
       		@endif
       	@endforeach
    </select>
</div>
<div class="form-group row">
	<label for="hastavendedor" class="col-lg-3 col-form-label requerido">Hasta Vendedor</label>
   	<select name="hastavendedor_id" id="hastavendedor_id" data-placeholder="Vendedor" class="col-lg-4 form-control required" data-fouc>
   		<option value="">-- Seleccionar vendedor --</option>
       	@foreach($vendedor_query as $key => $value)
       		@if( (int) $value->id == '999999')
       			<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
       		@else
       			<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
       		@endif
       	@endforeach
    </select>
</div>
