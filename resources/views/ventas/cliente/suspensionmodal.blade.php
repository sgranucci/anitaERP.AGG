<div class="modal fade" id="suspensionModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Suspensi&oacute;n del Cliente</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
    		<div class="form-group row">
   				<label for="tiposuspension" class="col-form-label">Tipo de suspensi&oacute;n</label>
        		<select name="modaltiposuspension_id" id="modaltiposuspension_id" data-placeholder="Tipo de suspensi&oacute;n" class="col-lg-6 form-control" data-fouc>
        			<option value="">-- Seleccionar tipo --</option>
        			@foreach($tiposuspensioncliente_query as $key => $value)
        				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
        			@endforeach
        		</select>
			</div>
		</form>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierrasuspensionModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptasuspensionModal" class="btn btn-primary">Suspende cliente</button>
      </div>
    </div>
  </div>
</div>
