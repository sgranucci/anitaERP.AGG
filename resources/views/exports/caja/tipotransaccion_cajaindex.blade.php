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
			<td colspan="6"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de tipos de transacciones de caja</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Operaci&oacute;n</th>
			<th>Abreviatura</th>
			<th>Signo</th>
			<th>Estado</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $operacionEnum[$data->operacion] ?? $data->operacion }}</td>
				<td>{{ $data->abreviatura }}</td>
				<td>{{ $signoEnum[$data->signo] ?? $data->signo }}</td>
				<td>{{ $estadoEnum[$data->estado] ?? $data->estado }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
