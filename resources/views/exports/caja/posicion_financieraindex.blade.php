<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="2" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="2">
				<h2 style="margin: 0; font-size: 18pt; font-weight: bold;">
					Posición financiera
					@if (($periodo_texto ?? '') !== '' || ! empty($empresa))
						— {{ $empresa->nombre ?? '' }}{{ ($periodo_texto ?? '') !== '' ? ' '.$periodo_texto : '' }}
					@endif
				</h2>
			</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Concepto</th>
			<th>Importe</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($filas as $fila)
			<tr>
				<td>{{ $fila['etiqueta'] ?? '' }}</td>
				<td>{{ number_format((float) ($fila['valor'] ?? 0), 2, ',', '.') }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
