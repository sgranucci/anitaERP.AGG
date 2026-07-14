<div class="modal fade" id="anulacionModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Anulacion de Item</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          	<div class="form-group">
            	<label for="recipient-name" class="col-form-label">Total kilos</label>
            	<input type="text" size="6" style="font-weight: bold; text-align: center;" id="totanulacionPares" name="totanulacionPares" readonly></input>
          	</div>
			<div class="form-group row" id="motivocierrepedido">
   				<label for="motivo anulacion asignado" class="col-form-label">Motivo anulacion</label>
        		<span id="nombremotivoanulacion" class="col-lg-6 form-control"></span>
			</div>
			<div class="form-group row">
   				<label for="motivoanulacion" class="col-form-label requerido">Motivo</label>
        		<select name="motivoanulacion_id" id="motivoanulacion_id" data-placeholder="Motivo" class="col-lg-6 form-control required" data-fouc>
        			<option value="">-- Seleccionar motivo --</option>
        			@foreach($motivocierrepedido_query as $key => $value)
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endforeach
        		</select>
			</div>			
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraanulacionModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaanulacionModal" class="btn btn-primary">Confirma</button>
      </div>
    </div>
  </div>
</div>
