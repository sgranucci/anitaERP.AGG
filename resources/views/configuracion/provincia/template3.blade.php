<template id="template-renglon-cuentacontableiibb">
	<tr class="item-cuentacontableiibb">
		<td>
			<select name="empresa_ids[]" data-placeholder="Empresa" class="empresa form-control required" required data-fouc>
				@foreach($empresa_query as $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
				@endforeach
			</select>
		</td>
		<td>
			<div class="form-group row" id="cuenta">
				<input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="1" />
				<input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="" >
				<input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="" >
				<button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuentacontable tooltipsC">
						<i class="fa fa-search text-primary"></i>
				</button>
				<input type="text" style="WIDTH: 200px;HEIGHT: 38px" class="codigocuentacontable interno form-control" name="codigos[]" value="" >
				<input type="text" style="WIDTH: 400px;HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombres[]" value="" >
				<input type="hidden" class="codigo_previo" name="codigo_previos[]" value="" >
			</div>
		</td>	
		<td>
			<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuentacontableiibb tooltipsC">
				<i class="fa fa-times-circle text-danger"></i>
			</button>
			<input type="hidden" name="creousuario_cuentacontable_ids[]" class="form-control creousuario_cuentacontable_id" value="{{ auth()->id() }}"/>
		</td>
	</tr>
</template>