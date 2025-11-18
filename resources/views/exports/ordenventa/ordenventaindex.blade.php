<h2> Ordenes de Venta </h2>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Fecha</th>
		<th>Nro. Orden Vta.</th>
		<th>Empresa</th>
		<th>Tratamiento</th>
		<th>Centro de Costo</th>
		<th>Cliente</th>
		<th>Monto</th>
		<th>Moneda</th>
		<th>Estado</th>
		<th>Detalle</th>
	</tr>
  	</thead>
    <tbody>
		@foreach ($ordenventa as $data)
		<tr>
			<td>{{$data->id}}</td>
			<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
			<td>{{$data->numeroordenventa ?? ''}}</td>
			<td>{{$data->nombreempresa ?? ''}}</td>
			<td>{{$data->tratamiento ?? ''}}</td>
			<td>{{$data->nombrecentrocosto ?? '' }}</td>
			<td>{{$data->nombrecliente ?? ''}}</td>
			<td>{{number_format($data->monto,2) ?? ''}}</td>
			<td>{{$data->abreviaturamoneda}}</td>
			<td>{{$data->estado}}</td>
			<td>{{$data->detalle}}</td>
		</tr>
		@endforeach
	</tbody>
</table>
