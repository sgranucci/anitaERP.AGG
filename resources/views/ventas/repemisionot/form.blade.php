<div class="row">
	<div class="col-sm-12">
		<div class="form-group row">
			<label for="ot" class="col-lg-3 col-form-label requerido">Ordenes de trabajo a imprimir</label>
    		<div class="col-lg-8">
    			<input type="text" name="ordenestrabajo" id="ordenestrabajo" class="form-control" value="{{old('ordenestrabajo')}}" required>
			</div>
		</div>
		
		<div class="form-group row">
    		<label for="tipoemision" class="col-lg-3 col-form-label requerido">Tipo de emisi&oacute;n</label>
			<select name="tipoemision" class="col-lg-3 form-control" required>
    			<option value="">-- Elija tipo de emisi&oacute; --</option>
       			@foreach($tipoemision_enum as $value => $tipoemision)
       				@if( $value == 'COMPLETA' )
       					<option value="{{ $value }}" selected="select">{{ $tipoemision }}</option>    
       				@else
       					<option value="{{ $value }}">{{ $tipoemision }}</option>    
       				@endif
       			@endforeach
			</select>
		</div>

	</div>
</div>
