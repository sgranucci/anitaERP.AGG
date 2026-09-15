<div class="row">
	<div class="col-sm-12">
		<div class="form-group row">
			<label for="ordenestrabajo" class="col-lg-3 control-label text-right pr-2 requerido">Ordenes de trabajo a imprimir</label>
    		<div class="col-lg-8">
    			<input type="text" name="ordenestrabajo" id="ordenestrabajo" class="form-control" value="{{old('ordenestrabajo')}}" required>
			</div>
		</div>
		
		<div class="form-group row">
    		<label for="tipoemision" class="col-lg-3 control-label text-right pr-2 requerido">Tipo de emisión</label>
			<select name="tipoemision" id="tipoemision" class="col-lg-3 form-control" required>
    			<option value="">-- Elija tipo de emisión --</option>
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
