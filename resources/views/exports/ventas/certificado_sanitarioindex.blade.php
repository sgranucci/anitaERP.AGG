<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="11" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="11"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado certificados sanitarios SENASA</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nro</th>
			<th>Fecha</th>
			<th>Cami&oacute;n</th>
			<th>Reparto</th>
			<th>Precinto</th>
			<th>Est. destino</th>
			<th>Kilos</th>
			<th>Cajas</th>
			<th>Nro. interno</th>
			<th>Nro. patag&oacute;nico</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->etiqueta }}</td>
				<td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
				<td>{{ $data->camion->dominio ?? '' }}</td>
				<td>{{ $data->transporte->nombre ?? '' }}</td>
				<td>{{ $data->precinto }}</td>
				<td>{{ $data->establecimiento_destino ?: '' }}</td>
				<td>{{ number_format((float) ($data->kilos_total ?? 0), 2, ',', '.') }}</td>
				<td>{{ number_format((float) ($data->cajas_total ?? 0), 2, ',', '.') }}</td>
				<td>{{ $data->nro_cert_interno ?: '' }}</td>
				<td>{{ $data->nro_cert_patagonico ?: '' }}</td>
			</tr>
		@endforeach
		@if (!empty($totalesListado))
			<tr>
				<td colspan="7"><strong>TOTAL</strong></td>
				<td><strong>{{ number_format((float) $totalesListado['kilos'], 2, ',', '.') }}</strong></td>
				<td><strong>{{ number_format((float) $totalesListado['cajas'], 2, ',', '.') }}</strong></td>
				<td></td>
				<td></td>
			</tr>
		@endif
	</tbody>
</table>
