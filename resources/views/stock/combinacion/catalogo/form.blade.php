<div class="form-group row">
	<label for="mventa_id" class="col-lg-4 col-form-label requerido">Marca</label>
	<select id="mventa_id" name="mventa_id" class="col-lg-8 form-control">
   		<option value="">-- Seleccionar --</option>
        @foreach($mventa_query as $key => $value)
          	@if( (int) $value->id == (int) old('mventa_id'))
           		<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
           	@else
           		<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
          	@endif
        @endforeach
	</select>
</div>
<div class="form-group row">
	<label for="categoria_id" class="col-lg-4 col-form-label">Categor&iacute;a</label>
	<select id="categoria_id" name="categoria_id" class="col-lg-8 form-control">
   		<option value="">-- Seleccionar --</option>
        	<option value="T" selected>Todas las categor&iacute;as</option>    
        @foreach($categoria_query as $key => $value)
        	<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
    	@endforeach
   	</select>
</div>
<div class="form-group row">
	<label for="subcategoria_id" class="col-lg-4 col-form-label">Sub Categor&iacute;a</label>
	<select id="subcategoria_id" name="subcategoria_id[]" multiple class="col-lg-8 form-control">
   		<option value="">-- Seleccionar --</option>
        	<option value="T" selected>Todas las subcategor&iacute;as</option>    
        @foreach($subcategoria_query as $key => $value)
        	<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
    	@endforeach
   	</select>
</div>
<div class="form-group row">
	<label for="precio" class="col-lg-4 col-form-label requerido">Incluye precios</label>
   	<select name="precio" id="precio" data-placeholder="Incluye Precios" class="col-lg-2 form-control required" data-fouc required>
   		<option value="">-- Seleccionar inclusión de precios --</option>
       	@foreach($precios_enum as $key => $value)
       		@if( $key == 'S')
       			<option value="{{ $key }}" selected="select">{{ $value }}</option>    
       		@else
       			<option value="{{ $key }}">{{ $value }}</option>    
       		@endif
       	@endforeach
    </select>
</div>
@include('includes.stock.rangolinea')

