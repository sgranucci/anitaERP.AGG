<div class="form-group row">
	<label for="file" class="col-lg-3 col-form-label requerido">Archivo</label>
	<div class="col-lg-8">
		<input type="file" name="file" class="form-control" value="" required/>
	</div>
</div>
<div class="form-group row">
	<label for="tipoimportacion" class="col-lg-3 col-form-label requerido">Tipo de importación</label>
   	<select name="tipoimportacion" id="tipoimportacion" data-placeholder="Tipo de importación" class="col-lg-4 form-control required" data-fouc required>
   		<option value="">-- Seleccionar tipos de importación --</option>
       	@foreach($tipoimportacion_enum as $key => $value)
       		@if( $key == 'STOCKPRECIO')
       			<option value="{{ $key }}" selected="select">{{ $value }}</option>
       		@else
       			<option value="{{ $key }}">{{ $value }}</option>
       		@endif
       	@endforeach
    </select>
</div>
<div class="form-group row">
	<label for="tienda" class="col-lg-3 col-form-label requerido">Tienda</label>
   	<select name="tienda" id="tienda" data-placeholder="Tienda" class="col-lg-4 form-control required" data-fouc required>
   		<option value="">-- Seleccionar tienda --</option>
       	@foreach($tienda_enum as $key => $value)
  			<option value="{{ $key }}">{{ $value }}</option>
       	@endforeach
    </select>
</div>
