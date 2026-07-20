@php
	$esExcel = ! empty($esExcel);
	$cliente_premio_uifs = $cliente_premio_uifs ?? collect();
	$subtitulo = 'Generado '.date('d/m/Y H:i').' — '.(is_countable($cliente_premio_uifs) ? count($cliente_premio_uifs) : 0).' registro(s)';
	$formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
	$autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
	$fmtMonto = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
		$n = (float) $v;
		if ($esExcel && $autoExcelNum) {
			return number_format($n, 2, '.', '');
		}
		if ($esExcel) {
			return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
		}
		return number_format($n, 2, ',', '.');
	};
@endphp
<table>
	@if (! empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="9" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Premios UIF</h2></td>
		</tr>
		<tr>
			<td colspan="9"><strong>{{ $subtitulo }}</strong></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Nombre</th>
			<th>Sala</th>
			<th>Juego</th>
			<th>Fecha Entrega</th>
			<th>Monto</th>
			<th>Posici&oacute;n</th>
			<th>N&uacute;mero TITO</th>
			<th>Forma de Pago</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($cliente_premio_uifs as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->nombrecliente }}</td>
				<td>{{ $data->nombresala }}</td>
				<td>{{ $data->nombrejuego }}</td>
				<td>{{ $data->fechaentrega }}</td>
				<td>{{ $fmtMonto($data->monto ?? 0) }}</td>
				<td>{{ $data->posicion ?? '' }}</td>
				<td>{{ $data->numerotito ?? '' }}</td>
				<td>{{ $data->nombreformapago }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
