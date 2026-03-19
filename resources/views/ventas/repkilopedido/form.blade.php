<div class="form-group row">
	<label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
	<div class="col-lg-4">
		<input type="date" name="desdefecha" id="desdefecha" class="form-control" value="{{date('Y-m-d')}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
	<div class="col-lg-4">
		<input type="date" name="hastafecha" id="hastafecha" class="form-control" value="{{date('Y-m-d')}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="desdetransporte" class="col-lg-3 col-form-label">Desde Reparto</label>
	<input type="hidden" class="col-form-label desdetransporte_id" id="desdetransporte_id" name="desdetransporte_id" value="" >
	<input type="text" class="col-lg-1 form-control codigodesdetransporte" id="codigodesdetransporte" name="codigodesdetransporte" value="" >
	<input type="text" class="col-lg-3 form-control nombredesdetransporte" id="nombredesdetransporte" name="nombredesdetransporte" value="" readonly>
	<button type="button" title="Consulta repartos" style="padding:1;" class="btn-accion-tabla consultadesdetransporte tooltipsC">
		<i class="fa fa-search text-primary"></i>
	</button>
</div>		
<div class="form-group row">
	<label for="hastatransporte" class="col-lg-3 col-form-label">Hasta Reparto</label>
	<input type="hidden" class="col-form-label hastatransporte_id" id="hastatransporte_id" name="hastatransporte_id" value="" >
	<input type="text" class="col-lg-1 form-control codigohastatransporte" id="codigohastatransporte" name="codigohastatransporte" value="" >
	<input type="text" class="col-lg-3 form-control nombrehastatransporte" id="nombrehastatransporte" name="nombrehastatransporte" value="" readonly>
	<button type="button" title="Consulta repartos" style="padding:1;" class="btn-accion-tabla consultahastatransporte tooltipsC">
		<i class="fa fa-search text-primary"></i>
	</button>
</div>		
<div class="form-group row">
  	<label for="tipolistado" class="col-lg-3 col-form-label requerido">Tipo de listado</label>
	<select name="tipolistado" class="col-lg-3 form-control" required>
    	@foreach($tipolistado_enum as $value => $tipolistado)
      		<option value="{{ $value }}">{{ $tipolistado }}</option>    
       	@endforeach
	</select>
</div>

<div class="form-group row">
  	<label for="estado" class="col-lg-3 col-form-label requerido">Estado de pedidos</label>
	<select name="estado" class="col-lg-3 form-control" required>
    	@foreach($estado_enum as $value => $estado)
    		<option value="{{ $value }}">{{ $estado }}</option>    
       	@endforeach
	</select>
</div>
