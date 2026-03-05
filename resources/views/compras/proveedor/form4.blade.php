<div class="form4" style="display: none">
	<div class="row">
		<div class="col-sm-6">
               	<!-- textarea -->
               	<div class="form-group">
               		<label>Leyendas</label>
               		<textarea name="leyenda" id="leyenda" class="form-control" rows="20" placeholder="Leyendas ...">{{old('leyenda', $data->leyenda ?? '')}}</textarea>
               	</div>
        </div>
		<div class="col-sm-6">
                <a href="#" class="btn btn-outline-info btn-sm" onclick="imprimirHtml('printableArea', 'enc')">
                        <i class="fa fa-fw fa-print"></i> Imprimir leyendas
                </a>
        </div>
   </div>
</div>
