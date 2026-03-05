<h2> Localidades </h2>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Nombre</th>
		<th>CUIT</th>
		<th>Actividad</th>
		<th>Fecha Inicio</th>
	</tr>
  	</thead>
    <tbody>
		@foreach ($padron_mipymes as $data)
			<tr data-entry-id="{{ $data->id }}">
				<td>{{$data->id}}</td>
				<td>{{$data->nombre}}</td>
				<td>{{$data->cuit}}</td>
				<td>{{$data->actividad}}</td>
				<td>{{date("d/m/Y", strtotime($data->fechainicio ?? ''))}}</td>
			</tr>
		@endforeach
	</tbody>
</table>
