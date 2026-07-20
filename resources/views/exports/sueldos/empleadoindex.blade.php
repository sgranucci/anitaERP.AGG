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
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de empleados</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Legajo</th>
			<th>Nombre</th>
			<th>CUIL</th>
			<th>Empresa</th>
			<th>Estado</th>
			<th>Categor&iacute;a</th>
			<th>Centro de costo</th>
			<th>Fecha ingreso</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			@php
				$estadoLabel = \App\Support\Sueldos\EmpleadoEstados::label($data->estado ?? null);
			@endphp
			<tr>
				<td>{{ $data->legajo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->cuil }}</td>
				<td>{{ $data->nombreempresa ?? optional($data->empresa)->nombre }}</td>
				<td>{{ $estadoLabel }}</td>
				<td>{{ optional($data->categoria)->descripcion }}</td>
				<td>{{ optional($data->centrocosto)->nombre }}</td>
				<td>{{ optional($data->fecha_ingreso)->format('d/m/Y') }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
