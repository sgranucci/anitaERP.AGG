<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="5" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="5"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Tipos de sanción</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Código</th>
			<th>Nombre</th>
			<th>Clase</th>
			<th>Activo</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ \App\Models\Sueldos\Tipo_Sancion_Sueldos::etiquetaClase($data->clase) }}</td>
				<td>{{ $data->activo ? 'Sí' : 'No' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
