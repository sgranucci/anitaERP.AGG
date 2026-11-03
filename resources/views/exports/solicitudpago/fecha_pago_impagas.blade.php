@php
    $esExcel = ! empty($esExcel);
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
    $fmtFecha = function ($fecha) {
        if (! $fecha) {
            return '';
        }

        return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
    };
@endphp
<table>
	<tbody>
		<tr>
			<td colspan="16"><strong style="font-size: 16pt;">Informe de solicitudes de pago</strong></td>
		</tr>
		<tr>
			<td colspan="16" style="font-size: 10pt; color: #444;">Generado {{ date('d/m/Y H:i') }}</td>
		</tr>
		<tr>
			<td colspan="16" style="font-size: 10pt; color: #444;">{{ $subtitulo }}</td>
		</tr>
		<tr>
			<td colspan="16" style="font-size: 10pt; color: #444;">
				Registros: {{ (int) ($totales['registros'] ?? 0) }}
				&middot; Importe: {{ number_format((float) ($totales['monto'] ?? 0), 2, ',', '.') }}
			</td>
		</tr>
		<tr>
			<td colspan="16" style="font-size: 10pt; color: #444;">L&iacute;neas: {{ (int) ($totales['registros'] ?? 0) }}</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>Empresa</th>
			<th>Numero</th>
			<th>Fecha</th>
			<th>Vence</th>
			<th>Fecha de pago</th>
			<th>Tratamiento</th>
			<th>Sector</th>
			<th>Concepto</th>
			<th>Forma de pago</th>
			<th>N.Pro.</th>
			<th>Proveedor</th>
			<th>Mon</th>
			<th>Importe</th>
			<th>Estado</th>
			<th>Refer.</th>
			<th>Observacion</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($filas as $fila)
			<tr>
				<td @if (! empty($fila->es_total)) style="font-weight: bold;" @endif>{{ $fila->nombreempresa }}</td>
				<td>{{ $fila->codigo }}{{ ! empty($fila->es_madre_plan) ? ' (Madre)' : (! empty($fila->es_hija) ? ' (Hija)' : '') }}</td>
				<td>{{ $fmtFecha($fila->fecha ?? null) }}</td>
				<td>{{ $fmtFecha($fila->fecha_vencimiento ?? null) }}</td>
				<td>{{ $fmtFecha($fila->fecha_pago ?? null) }}</td>
				<td>{{ $fila->tratamiento_label }}</td>
				<td>{{ trim(($fila->sector_codigo ? $fila->sector_codigo.' ' : '').($fila->sector_nombre ?? '')) }}</td>
				<td>{{ $fila->concepto_nombre }}</td>
				<td>{{ $fila->forma_pago_nombre }}</td>
				<td>{{ $fila->proveedor_codigo }}</td>
				<td>{{ $fila->proveedor_nombre }}</td>
				<td>{{ $fila->moneda }}</td>
				<td @if (! empty($fila->es_total)) style="font-weight: bold;" @endif>{{ $fmtMonto($fila->monto) }}</td>
				<td>{{ $fila->estado_label }}</td>
				<td>{{ $fila->referencia }}</td>
				<td>{{ $fila->observacion }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
