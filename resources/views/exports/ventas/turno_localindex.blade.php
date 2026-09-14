<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="7" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="7"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de turnos (Facturación Local)</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Código</th>
			<th>Nombre</th>
			<th>Empresa</th>
			<th>Horario</th>
			<th>Orden</th>
			<th>Activo</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo ?? '' }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->empresa->nombre ?? '' }}</td>
				<td>{{ $data->etiquetaHorario() }}</td>
				<td>{{ $data->orden }}</td>
				<td>{{ $data->activo ? 'Sí' : 'No' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
