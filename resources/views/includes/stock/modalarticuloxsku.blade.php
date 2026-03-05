<div class="modal fade" id="articuloxskuModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Art&iacute;culos por SKU</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
			<div class="form-group row">
   				<label for="articuloxsku" class="col-form-label requerido">Art&iacute;culo</label>
        		<select name="articuloxsku_id" id="articuloxsku_id" data-placeholder="Art&iacute;culo" class="col-lg-6 form-control required" data-fouc>
        			<option value="">-- Seleccionar art&iacute;culo --</option>
        			@foreach($articuloxsku_query as $key => $value)
        				<option value="{{ $value->id }}">{{ $value->sku }}-{{ $value->descripcion }}</option>    
        			@endforeach
        		</select>
			</div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraarticuloxskuModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaarticuloxskuModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
