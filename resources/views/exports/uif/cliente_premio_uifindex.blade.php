@php
	$esExcel = ! empty($esExcel);
	$cliente_premio_uifs = $cliente_premio_uifs ?? collect();
	$lineaGenerado = 'Generado '.date('d/m/Y H:i').' — '.(is_countable($cliente_premio_uifs) ? count($cliente_premio_uifs) : 0).' registro(s)';
	$subtituloFiltros = trim((string) ($subtituloFiltros ?? ''));
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
	$fmtTextoExcel = function ($v) use ($esExcel) {
		$s = trim((string) ($v ?? ''));
		if ($s === '') {
			return '';
		}

		return $esExcel ? "\t".$s : $s;
	};
	$fmtFecha = function ($v) {
		if (empty($v)) {
			return '';
		}
		try {
			return \Carbon\Carbon::parse($v)->format('d/m/Y H:i');
		} catch (\Throwable $e) {
			return (string) $v;
		}
	};
@endphp
<table>
	@if (! empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="10" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Premios UIF</h2></td>
		</tr>
		<tr>
			<td colspan="10"><strong>{{ $lineaGenerado }}</strong></td>
		</tr>
		@if ($subtituloFiltros !== '')
			<tr>
				<td colspan="10"><strong>Filtros: {{ $subtituloFiltros }}</strong></td>
			</tr>
		@endif
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Origen</th>
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
				<td>{{ $fmtTextoExcel($data->id) }}</td>
				<td>{{ \App\Support\Uif\ClienteUifOrigenPcSupport::labelOrigen((string) ($data->anita_origen ?? '')) }}</td>
				<td>{{ $data->nombrecliente }}</td>
				<td>{{ $data->nombresala }}</td>
				<td>{{ $data->nombrejuego }}</td>
				<td>{{ $fmtFecha($data->fechaentrega) }}</td>
				<td>{{ $fmtMonto($data->monto ?? 0) }}</td>
				<td>{{ $fmtTextoExcel($data->posicion ?? '') }}</td>
				<td>{{ $fmtTextoExcel($data->numerotito ?? '') }}</td>
				<td>{{ $data->nombreformapago }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
