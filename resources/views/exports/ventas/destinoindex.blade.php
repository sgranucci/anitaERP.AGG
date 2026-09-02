<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="8" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Destinos SENASA</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Código zona</th>
			<th>Zona de venta</th>
			<th>Localidad</th>
			<th>Provincia</th>
			<th>País</th>
			<th>Patagónico</th>
			<th>Código de localidad SENASA</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->zonavta->nombre ?? '' }}</td>
				<td>{{ $data->localidad }}</td>
				<td>{{ $data->provincia }}</td>
				<td>{{ $data->pais_codigo }}</td>
				<td>{{ $data->patagonico ? 'Sí' : 'No' }}</td>
				<td>{{ $data->codigo_localidad_senasa }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
