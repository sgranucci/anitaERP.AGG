<?php use App\Models\Sueldos\Tipo_Ausencia_Sueldos; ?>
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
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Tipos de ausencia</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Nombre</th>
			<th>Categor&iacute;a</th>
			<th>Descuenta vacaciones</th>
			<th>Paga</th>
			<th>C&oacute;mputo d&iacute;as</th>
			<th>Tope/a&ntilde;o</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ Tipo_Ausencia_Sueldos::etiquetaCategoria($data->categoria) }}</td>
				<td>{{ $data->afecta_saldo_vacaciones ? 'Sí' : 'No' }}</td>
				<td>{{ $data->goza_sueldo ? 'Sí' : 'No' }}</td>
				<td>{{ Tipo_Ausencia_Sueldos::etiquetaTipoDias($data->tipo_dias) }}</td>
				<td>{{ $data->tope_dias_anio ?? '' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
