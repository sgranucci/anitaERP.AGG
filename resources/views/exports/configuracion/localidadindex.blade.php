<h2> Localidades </h2>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Nombre</th>
		<th>Código Postal</th>
		<th>Código Anita</th>
		<th>Provincia</th>
		<th>Código SENASA</th>
	</tr>
  	</thead>
    <tbody>
		@foreach ($localidades as $data)
			<tr data-entry-id="{{ $data->id }}">
				<td>{{$data->id}}</td>
				<td>{{$data->nombre}}</td>
				<td>{{$data->codigopostal}}</td>
				<td>{{$data->codigo}}</td>
				<td>{{$data->nombreprovincia??''}}</td>
				<td>{{$data->codigosenasa}}</td>
			</tr>
		@endforeach
	</tbody>
</table>
