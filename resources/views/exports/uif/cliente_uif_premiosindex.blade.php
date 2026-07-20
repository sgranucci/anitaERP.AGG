@php
    $esExcel = ! empty($esExcel);
    $premios = $premios ?? collect();
    $nombreCliente = $cliente_uif->nombre ?? '';
    $docCliente = $cliente_uif->numerodocumento ?? '';
    $subtitulo = trim($nombreCliente);
    if ($docCliente !== '') {
        $subtitulo .= ($subtitulo !== '' ? ' — ' : '').'Doc. '.$docCliente;
    }
    $subtitulo = trim($subtitulo.' · Generado '.date('d/m/Y H:i').' — '.(is_countable($premios) ? count($premios) : 0).' registro(s)', ' ·');
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
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="8" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Premios del cliente UIF</h2></td>
		</tr>
		<tr>
			<td colspan="8"><strong>{{ $subtitulo }}</strong></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Fecha entrega</th>
			<th>Sala</th>
			<th>Juego</th>
			<th>Nro. TITO</th>
			<th>Monto</th>
			<th>Posici&oacute;n</th>
			<th>Forma de pago</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($premios as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>
					@if (!empty($data->fechaentrega))
						{{ \Carbon\Carbon::parse($data->fechaentrega)->format('d/m/Y H:i') }}
					@endif
				</td>
				<td>{{ $data->nombresala }}</td>
				<td>{{ $data->nombrejuego }}</td>
				<td>{{ $data->numerotito ?? '' }}</td>
				<td>{{ $fmtMonto($data->monto ?? 0) }}</td>
				<td>{{ $data->posicion ?? '' }}</td>
				<td>{{ $data->nombreformapago }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
