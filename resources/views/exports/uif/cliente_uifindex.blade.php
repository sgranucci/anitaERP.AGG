<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="10" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de clientes UIF</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Tipo doc.</th>
			<th>N&uacute;mero de doc.</th>
			<th>Domicilio</th>
			<th>Localidad</th>
			<th>Provincia</th>
			<th>Pa&iacute;s</th>
			<th>Tel&eacute;fono</th>
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
