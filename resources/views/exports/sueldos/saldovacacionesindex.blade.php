<?php use App\Support\Sueldos\EmpleadoEstados; ?>
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
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Saldos de vacaciones</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Empresa</th>
			<th>Legajo</th>
			<th>Empleado</th>
			<th>Estado</th>
			<th>Ingreso</th>
			<th>Devengado</th>
			<th>Consumido</th>
			<th>Saldo</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->empresa_nombre }}</td>
				<td>{{ $data->legajo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ EmpleadoEstados::label($data->estado) }}</td>
				<td>{{ $data->fecha_ingreso ? \Carbon\Carbon::parse($data->fecha_ingreso)->format('d/m/Y') : '' }}</td>
				<td>{{ number_format((float) $data->devengado, 2, '.', '') }}</td>
				<td>{{ number_format((float) $data->consumido, 2, '.', '') }}</td>
				<td>{{ number_format((float) $data->saldo, 2, '.', '') }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
