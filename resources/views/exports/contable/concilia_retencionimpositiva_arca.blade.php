<h2> Concilia retenciones impositivas </h2>
<table> 
	<thead>
	<tr>
		<th>Empresa</th>
		<th>Codigo Cliente</th>
		<th>Nombre Cliente</th>
		<th>Cuit</th>
		<th>Monto segun ARCA</th>
		<th>Monto segun Sistema</th>
	</tr>
  	</thead>
    <tbody>
		@foreach ($arrayNoEncontroEnArca as $data)
			<tr>
				<td>{{$data['empresa_id']}}</td>
				<td>{{$data['codigocliente']}}</td>
				<td>{{$data['nombrecliente']}}</td>
				<td>{{$data['cuit']}}</td>
				<td>{{$data['montoarca']}}</td>
				<td>{{$data['montosistema']}}</td>
			</tr>
		@endforeach
		@foreach ($arrayNoEncontroEnSistema as $data)
			<tr>
				<td>{{$data['empresa_id']}}</td>
				<td>{{$data['codigocliente']}}</td>
				<td>{{$data['nombrecliente']}}</td>
				<td>{{$data['cuit']}}</td>
				<td>{{$data['montoarca']}}</td>
				<td>{{$data['montosistema']}}</td>
			</tr>
		@endforeach		
	</tbody>
</table>