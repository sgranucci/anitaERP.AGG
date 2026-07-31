<div class="form-group row">
	<label class="col-lg-3 col-form-label requerido">Provincia</label>
	<div class="form-group row">
		<input type="hidden" id="provincia_id_previa" name="provincia_id_previa" value="" >
		<input type="hidden" id="desc_provincia" name="desc_provincia" value="" >
		<input type="hidden" class="col-form-label provincia_id" id="provincia_id" name="provincia_id" value="" >
		<input type="text" class="form-control col-lg-3 codigoprovincia" id="codigoprovincia" name="codigoprovincia" value="" >
		<input type="text" class="form-control col-lg-8 nombreprovincia" id="nombreprovincia" name="nombreprovincia" value="" readonly>
		<button type="button" title="Consulta provincias" style="padding:1;" class="btn-accion-tabla consultaprovincia tooltipsC">
			<i class="fa fa-search text-primary"></i>
		</button>
	</div>
</div>
<div class="form-group row" id="tipopadron">
    <label for="tipopadron" class="col-lg-3 col-form-label requerido">Tipo de Padrón</label>
	<select name="tipopadron" class="col-lg-3 form-control">
    	<option value="">-- Elija tipo de padrón --</option>
        @foreach ($tipopadron_enum as $value => $tipopadron)
        	<option value="{{ $value }}">{{ $tipopadron }}</option>
        @endforeach
	</select>
</div>
<div class="form-group row">
	<label for="file" class="col-lg-3 col-form-label">Archivo (upload)</label>
	<div class="col-lg-8">
		<input type="file" name="file" id="file" class="form-control" value=""/>
		<small class="form-text text-muted">
			CABA (CABA/AGIP): TXT <code>ARDJU….TXT</code>.
			ARBA: ZIP <code>PadronRGS….zip</code> o TXT Per/Ret.
			Archivos muy grandes (~100&nbsp;MB+) conviene usar la ruta en servidor.
		</small>
	</div>
</div>
<div class="form-group row">
	<label for="ruta_servidor" class="col-lg-3 col-form-label">Ruta en servidor</label>
	<div class="col-lg-8">
		<input type="text" name="ruta_servidor" id="ruta_servidor" class="form-control"
			placeholder="/home/sergio/padroncaba/ARDJU008082026.TXT"
			value="{{ old('ruta_servidor') }}"/>
		<small class="form-text text-muted">
			Absoluta, bajo <code>/home/sergio/padroncaba</code>, <code>/home/sergio/padronarba</code>
			o <code>storage/app</code>. Si completa este campo, no hace falta subir el archivo.
		</small>
	</div>
</div>
