<h2> Tickets </h2>s
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Fecha</th>
		<th>Sala</th>
		<th>Sector</th>
		<th>Area de destino</th>
		<th>Categoría</th>
		<th>Subcategoría</th>
		<th>Estado</th>
		<th>Detalle</th>
	</tr>
  	</thead>
    <tbody>
		@foreach ($ticket as $data)
		<tr>
			<td>{{$data->id}}</td>
			<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
			<td>{{$data->nombresala ?? ''}}</td>
			<td>{{$data->nombresector ?? ''}}</td>
			<td>{{$data->nombreareadestino ?? ''}}</td>
			<td>{{$data->nombrecategoria_ticket ?? ''}}</td>
			<td>{{$data->nombresubcategoria_ticket ?? ''}}</td>
			<td>{{$data->estado}}</td>
			<td>{{$data->detalle}}</td>
		</tr>
		@endforeach
	</tbody>
</table>
