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
			<td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de rendiciones de máquinas</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Fecha</th>
			<th>Turno</th>
			<th>Empresa</th>
			<th>Resultado</th>
			<th>Transferencia</th>
			<th>Total ingreso</th>
			<th>Total salida</th>
			<th>Estado</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
				<td>{{ $data->turno_label }}</td>
				<td>{{ $data->empresa->nombre ?? '' }}</td>
				<td>{{ number_format((float) $data->resultado_turno, 2, '.', '') }}</td>
				<td>{{ number_format((float) $data->transferencia, 2, '.', '') }}</td>
				<td>{{ number_format((float) $data->total_ingreso, 2, '.', '') }}</td>
				<td>{{ number_format((float) $data->total_salida, 2, '.', '') }}</td>
				<td>{{ $data->estado_label }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
