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
			<td colspan="5"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Tablas de antigüedad</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>C&oacute;digo</th>
			<th>Descripci&oacute;n</th>
			<th>Tramos</th>
			<th>Activo</th>
			<th>Empresa</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->descripcion }}</td>
				<td>{{ $data->tramos_count ?? '' }}</td>
				<td>{{ $data->activo ? 'Sí' : 'No' }}</td>
				<td>{{ $data->empresa_id ?? 'Global' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
