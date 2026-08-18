<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="9" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Corridas de liquidación</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Empresa</th>
			<th>N&deg;</th>
			<th>Descripci&oacute;n</th>
			<th>Tipo</th>
			<th>Per&iacute;odo</th>
			<th>Fecha pago</th>
			<th>Estado</th>
			<th>No remunerativo</th>
			<th>Neto</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ optional($data->empresa)->nombre }}</td>
				<td>{{ $data->numero }}</td>
				<td>{{ $data->descripcion }}{{ $data->simulacion ? ' (Simulación)' : '' }}</td>
				<td>{{ $data->tipoLabel() }}</td>
				<td>{{ $data->periodo_mes ? sprintf('%02d/%04d', $data->periodo_mes, $data->periodo_anio) : $data->periodo }}</td>
				<td>{{ optional($data->fecha_pago)->format('d/m/Y') }}</td>
				<td>{{ $data->estadoLabel() }}</td>
				<td>{{ number_format((float) $data->total_no_remunerativo, 2, ',', '.') }}</td>
				<td>{{ number_format((float) $data->total_neto, 2, ',', '.') }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
