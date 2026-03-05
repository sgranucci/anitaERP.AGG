<div class="row">
	<div class="col-sm-12">
		<div class="form-group row">
			<label for="periodo" class="col-lg-3 col-form-label requerido">Período: </label>
			<div class="col-lg-3">
				<input type="month" name="periodo" value="" class="form-control periodo" placeholder="Periodo" required>
			</div>
		</div>
		<div class="form-group row">
			<label for="ot" class="col-lg-3 col-form-label requerido">Importa mayor a: </label>
    		<div class="col-lg-3">
    			<input type="number" name="limiteinformeuif" id="limiteinformeuif" class="form-control" value="{{config('uif.LIMITE_INFORME_UIF')}}" required>
			</div>
		</div>
	</div>
</div>
