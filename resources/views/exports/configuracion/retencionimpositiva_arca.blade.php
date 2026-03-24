<h2> Retenciones Impositivas Arca</h2>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Empresa</th>
		<th>Nombre</th>
		<th>CUIT</th>
		<th>Impuesto</th>
		<th>Fecha de Retención</th>
		<th>Certificado</th>
		<th>Monto Retención</th>
		<th>Fecha de Comprobante</th>
		<th>Nro. de Comprobante</th>
		<th>Descripcion Comprobante</th>
		<th>Fecha de Registración</th>
	</tr>
  	</thead>
	<tbody>
		@foreach ($retencionimpositiva_arca as $data)
		<tr>
			<td>{{$data->id}}</td>
			<td>{{$data->nombreempresa}}</td>
			<td>{{$data->nombre}}</td>
			<td>{{$data->cuit}}</td>
			<td>{{$data->descripcionimpuesto}}</td>
			<td>{{date("d/m/Y", strtotime($data->fecharetencion ?? ''))}}</td>
			<td>{{$data->numerocertificado}}</td>
			<td>{{$data->montoretencion}}</td>
			<td>{{date("d/m/Y", strtotime($data->fechacomprobante ?? ''))}}</td>
			<td>{{$data->numerocomprobante}}</td>
			<td>{{$data->descripcioncomprobante}}</td>
			<td>{{date("d/m/Y", strtotime($data->fecharegistracion ?? ''))}}</td>
		@endforeach		
	</tbody>
</table>