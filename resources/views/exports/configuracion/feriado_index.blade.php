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
			<td colspan="4"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de feriados</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Fecha</th>
			<th>Tipo</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->fecha ? \Illuminate\Support\Carbon::parse($data->fecha)->format('d/m/Y') : '' }}</td>
				<td>{{ $data->tipo }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
