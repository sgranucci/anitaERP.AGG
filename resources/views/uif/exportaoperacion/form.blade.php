<div class="row">
	<div class="col-sm-12">
		@include('includes.form-empresa-asignada', [
			'empresa_query' => $empresa_query,
			'empresa_id' => old('empresa_id'),
			'col_label' => 'col-lg-3',
			'col_input' => 'col-lg-5',
		])
		<div class="form-group row">
			<label for="periodo" class="col-lg-3 col-form-label requerido">Per&iacute;odo: </label>
			<div class="col-lg-3">
				<input type="text" name="periodo" id="periodo" value="{{ old('periodo', '') }}" class="form-control periodo" placeholder="AAAA-MM" autocomplete="off" required>
			</div>
		</div>
		<div class="form-group row">
			<label for="limiteinformeuif" class="col-lg-3 col-form-label requerido">Importe mayor a: </label>
    		<div class="col-lg-3">
    			<input type="number" name="limiteinformeuif" id="limiteinformeuif" class="form-control" value="{{ old('limiteinformeuif', config('uif.LIMITE_INFORME_UIF')) }}" required>
			</div>
		</div>
	</div>
</div>
