<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="4" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="4"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de agrupamientos de sueldos</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Descripci&oacute;n</th>
			<th>Fallo</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->descripcion }}</td>
				<td>{{ $data->fallo_tipo ?? 'Sin fallo' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
