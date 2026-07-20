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
			<td colspan="6"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Fallos de caja</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Tipo</th>
			<th>Orden</th>
			<th>Desde</th>
			<th>Hasta</th>
			<th>Sanci&oacute;n</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->tipo }}</td>
				<td>{{ $data->orden }}</td>
				<td>{{ number_format((float) $data->desde, 2, ',', '.') }}</td>
				<td>{{ number_format((float) $data->hasta, 2, ',', '.') }}</td>
				<td>{{ $data->sancion }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
