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
			<td colspan="7"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de conceptos de rendici&oacute;n de bingo</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Detalle</th>
			<th>Signo</th>
			<th>Porcentaje</th>
			<th>Empresa</th>
			<th>Estado</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->detalle }}</td>
				<td>{{ $data->signo }}</td>
				<td>
                    @if ($data->porcentaje !== null && $data->porcentaje !== '')
                        {{ number_format((float) $data->porcentaje, 4, ',', '.') }}%
                    @endif
                </td>
				<td>{{ $data->empresa->nombre ?? '' }}</td>
				<td>{{ $data->estado_label }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
