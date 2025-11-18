<div class="form-group row">
	<label class="col-lg-3 col-form-label requerido">Provincia</label>
	<div class="form-group row">
		<input type="hidden" id="provincia_id_previa" name="provincia_id_previa" value="" >
		<input type="hidden" id="desc_provincia" name="desc_provincia" value="" >
		<input type="hidden" class="col-form-label provincia_id" id="provincia_id" name="provincia_id" value="" >
		<input type="text" class="form-control col-lg-3 codigoprovincia" id="codigoprovincia" name="codigoprovincia" value="" >
		<input type="text" class="form-control col-lg-8 nombreprovincia" id="nombreprovincia" name="nombreprovincia" value="" readonly>
		<button type="button" title="Consulta provinciaes" style="padding:1;" class="btn-accion-tabla consultaprovincia tooltipsC">
			<i class="fa fa-search text-primary"></i>
		</button>
		<input type="hidden" name="nombreprovincia" id="nombreprovincia" class="form-control" value="">
	</div>
</div>					
<div class="form-group row">
	<label for="file" class="col-lg-3 col-form-label requerido">Archivo</label>
	<div class="col-lg-8">
		<input type="file" name="file" class="form-control" value="" required/>
	</div>
</div>


