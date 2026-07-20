<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="6" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="6"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de prendas</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Descripci&oacute;n</th>
			<th>Marca</th>
			<th>EPP</th>
			<th>Variantes</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->descripcion }}</td>
				<td>{{ $data->marca }}</td>
				<td>{{ $data->es_seguridad ? 'Sí' : 'No' }}</td>
				<td>{{ $data->variantes_count ?? 0 }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
