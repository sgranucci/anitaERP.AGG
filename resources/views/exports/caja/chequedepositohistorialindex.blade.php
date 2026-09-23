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
			<td colspan="9"><strong style="font-size: 16pt;">Historial de depósitos CHT</strong></td>
		</tr>
		<tr>
			<td colspan="9">Generado {{ date('d/m/Y H:i') }}</td>
		</tr>
		@if (!empty($subtitulo))
		<tr>
			<td colspan="9">{{ $subtitulo }}</td>
		</tr>
		@endif
		<tr>
			<td colspan="9">{{ (int) ($totalGrupos ?? 0) }} boletas · {{ (int) ($totalCheques ?? 0) }} cheques</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Fecha dep.</th>
			<th>Nro. boleta</th>
			<th>Cuenta</th>
			<th>Empresa</th>
			<th>Cheques</th>
			<th>Monto</th>
			<th>Monedas</th>
			<th>Estado</th>
			<th>Detalle estados</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($grupos as $g)
			@php
				$detalleEst = [];
				if (($g['estado_counts']['transito'] ?? 0) > 0) {
					$detalleEst[] = 'Tránsito '.$g['estado_counts']['transito'];
				}
				if (($g['estado_counts']['acreditado'] ?? 0) > 0) {
					$detalleEst[] = 'Acred. '.$g['estado_counts']['acreditado'];
				}
				if (($g['estado_counts']['rechazado'] ?? 0) > 0) {
					$detalleEst[] = 'Rech. '.$g['estado_counts']['rechazado'];
				}
				$monedasTxt = collect($g['totales_moneda'] ?? [])->map(function ($tm) {
					return $tm['moneda'].' '.number_format((float) $tm['monto'], 2, '.', '');
				})->implode(' | ');
			@endphp
			<tr>
				<td>{{ $g['fecha_deposito_dmy'] ?? $g['fecha_deposito'] }}</td>
				<td>{{ $g['nro_boleta'] !== '' ? $g['nro_boleta'] : '(sin boleta)' }}</td>
				<td>{{ $g['cuenta'] }}</td>
				<td>{{ $g['empresa'] }}</td>
				<td>{{ $g['cantidad'] }}</td>
				<td>{{ number_format((float) $g['monto'], 2, '.', '') }}</td>
				<td>{{ $monedasTxt }}</td>
				<td>{{ $g['estado_label'] }}</td>
				<td>{{ implode(' · ', $detalleEst) }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
