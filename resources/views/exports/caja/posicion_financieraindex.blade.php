@php
    $dias = $dias ?? [];
    $colspan = 2 + count($dias);
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="{{ $colspan }}">
				<h2 style="margin: 0; font-size: 18pt; font-weight: bold;">
					Posición financiera
				</h2>
			</td>
		</tr>
		<tr>
			<td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
		</tr>
		<tr>
			<td colspan="{{ $colspan }}">
				{{ $empresa->nombre ?? '' }}{{ ($periodo_texto ?? '') !== '' ? ' · Período '.$periodo_texto : '' }}
			</td>
		</tr>
	</tbody>
	@include('caja.posicion_financiera.partials.tabla_datos', [
		'filas' => $filas,
		'dias' => $dias,
		'modo' => 'excel',
	])
</table>
