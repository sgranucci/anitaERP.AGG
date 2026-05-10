<h2>Clientes UIF</h2>
<table>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Tipo doc.</th>
			<th>Número de doc.</th>
			<th>Domicilio</th>
			<th>Localidad</th>
			<th>Provincia</th>
			<th>País</th>
			<th>Teléfono</th>
			<th>Email</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($cliente_uifs as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->abreviaturatipodocumento }}</td>
				<td>{{ $data->numerodocumento }}</td>
				<td>{{ $data->domicilio }}</td>
				<td>{{ $data->nombrelocalidad ?? '' }}</td>
				<td>{{ $data->nombreprovincia ?? '' }}</td>
				<td>{{ $data->nombrepais ?? '' }}</td>
				<td>{{ $data->telefono }}</td>
				<td>{{ $data->email }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
