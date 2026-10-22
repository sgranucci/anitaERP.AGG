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
			<td colspan="10"><strong style="font-size: 16pt;">Cobranzas</strong></td>
		</tr>
		<tr>
			<td colspan="10">Generado {{ date('d/m/Y H:i') }}</td>
		</tr>
		@if (trim($subtitulo ?? '') !== '')
			<tr>
				<td colspan="10">{{ $subtitulo }}</td>
			</tr>
		@endif
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Empresa</th>
			<th>Número</th>
			<th>Fecha</th>
			<th>Cliente</th>
			<th>Detalle</th>
			<th>Estado</th>
			<th>Moneda</th>
			<th>Monto</th>
			<th>Prom. días</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($cobranza as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombreempresa }}</td>
				<td>{{ $data->numerotransaccion }}</td>
				<td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
				<td>{{ $data->nombrecliente }}</td>
				<td>{{ $data->detalle ?? '' }}</td>
				<td>{{ $data->estado ?? '' }}</td>
				<td>{{ $data->monedas->abreviatura ?? '' }}</td>
				<td>{{ number_format((float) $data->monto, 2, '.', '') }}</td>
				<td>
					@if (($data->promedio_pago_dias ?? null) !== null)
						{{ (int) $data->promedio_pago_dias }}
					@endif
				</td>
			</tr>
		@endforeach
	</tbody>
</table>
