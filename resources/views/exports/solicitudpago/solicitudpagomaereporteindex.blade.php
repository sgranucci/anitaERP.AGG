@php
    $colspan = (int) ($colspan ?? 15);
    $muestraCuota = ! empty($muestra_cuota);
    $incluirConcil = ! empty($incluir_conciliacion);
    $esExcel = ! empty($esExcel);
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtMonto = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        if ($v === null || $v === '') {
            return '';
        }
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
				<td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="{{ $colspan }}">
				<strong style="font-size: 16pt;">Informe de solicitudes de pago</strong>
			</td>
		</tr>
		<tr>
			<td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
				Generado {{ date('d/m/Y H:i') }}
			</td>
		</tr>
		@if (! empty($subtitulo))
			<tr>
				<td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
					{{ $subtitulo }}
				</td>
			</tr>
		@endif
		@if (! empty($totales))
			<tr>
				<td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
					Registros: {{ (int) ($totales['registros'] ?? 0) }}
					&middot; Importe: {{ number_format((float) ($totales['monto'] ?? 0), 2, ',', '.') }}
					@if ($incluirConcil)
						&middot; Concil. OK: {{ (int) ($totales['conciliadas_ok'] ?? 0) }}
						&middot; Concil. DIF: {{ (int) ($totales['conciliadas_dif'] ?? 0) }}
					@endif
				</td>
			</tr>
		@endif
		@if (is_countable($filas) && count($filas) > 0)
			<tr>
				<td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
					L&iacute;neas: {{ count($filas) }}
				</td>
			</tr>
		@endif
	</tbody>
	<thead>
		<tr>
			<th>Empresa</th>
			<th>Numero</th>
			<th>Fecha</th>
			<th>Vence</th>
			<th>Tratamiento</th>
			<th>Sector</th>
			<th>Concepto</th>
			<th>Forma de pago</th>
			<th>N.Pro.</th>
			<th>Proveedor</th>
			<th>Mon</th>
			<th>Importe</th>
			@if ($muestraCuota)
				<th>Monto cuota</th>
				<th>Cuota paga</th>
			@endif
			<th>Estado</th>
			<th>Refer.</th>
			<th>Observacion</th>
			@if ($incluirConcil)
				<th>SP Debe</th>
				<th>SP Haber</th>
				<th>Mayor Debe</th>
				<th>Mayor Haber</th>
				<th>Diff</th>
				<th>Concil.</th>
			@endif
		</tr>
	</thead>
	<tbody>
		@foreach ($filas as $fila)
			<tr>
				<td>{{ $fila->nombreempresa }}</td>
				<td>{{ $fila->codigo }}{{ ! empty($fila->es_madre_plan) ? ' (Madre)' : (! empty($fila->es_hija) ? ' (Hija)' : '') }}</td>
				<td>{{ $fila->fecha ? \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y') : '' }}</td>
				<td>{{ $fila->fecha_vencimiento ? \Carbon\Carbon::parse($fila->fecha_vencimiento)->format('d/m/Y') : '' }}</td>
				<td>{{ $fila->tratamiento_label }}</td>
				<td>{{ trim(($fila->sector_codigo ? $fila->sector_codigo.' ' : '').($fila->sector_nombre ?? '')) }}</td>
				<td>{{ $fila->concepto_nombre }}</td>
				<td>{{ $fila->forma_pago_nombre }}</td>
				<td>{{ $fila->proveedor_codigo }}</td>
				<td>{{ $fila->proveedor_nombre }}</td>
				<td>{{ $fila->moneda }}</td>
				<td>{{ $fmtMonto($fila->monto) }}</td>
				@if ($muestraCuota)
					<td>{{ $fmtMonto($fila->monto_cuota ?? 0) }}</td>
					<td>{{ (int) ($fila->cuota_paga ?? 0) ?: '' }}</td>
				@endif
				<td>{{ $fila->estado_label }}</td>
				<td>{{ $fila->referencia }}</td>
				<td>{{ $fila->observacion }}</td>
				@if ($incluirConcil)
					<td>{{ $fila->concil_sp_debe !== null ? $fmtMonto($fila->concil_sp_debe) : '' }}</td>
					<td>{{ $fila->concil_sp_haber !== null ? $fmtMonto($fila->concil_sp_haber) : '' }}</td>
					<td>{{ $fila->concil_mayor_debe !== null ? $fmtMonto($fila->concil_mayor_debe) : '' }}</td>
					<td>{{ $fila->concil_mayor_haber !== null ? $fmtMonto($fila->concil_mayor_haber) : '' }}</td>
					<td>{{ $fila->concil_diff !== null ? $fmtMonto($fila->concil_diff) : '' }}</td>
					<td>{{ $fila->concil_estado ?? '' }}</td>
				@endif
			</tr>
			@if ($muestraCuota && ! empty($fila->cuotas_detalle))
				@foreach ($fila->cuotas_detalle as $cuota)
					<tr>
						<td>{{ $fila->nombreempresa }}</td>
						<td>  → cuota {{ $cuota->nro_cuota }}</td>
						<td></td>
						<td>{{ $cuota->fecha_vencimiento ? \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') : '' }}</td>
						<td>Cuota plan</td>
						<td></td>
						<td>SP hija {{ $cuota->hija_codigo ? '#'.$cuota->hija_codigo : 'pendiente' }}</td>
						<td></td>
						<td></td>
						<td></td>
						<td>{{ $fila->moneda }}</td>
						<td>{{ $fmtMonto($cuota->monto) }}</td>
						@if ($muestraCuota)
							<td>{{ $fmtMonto($cuota->monto) }}</td>
							<td>{{ $cuota->nro_cuota }}</td>
						@endif
						<td>{{ $cuota->hija_estado_label }}</td>
						<td>{{ $fila->codigo }}</td>
						<td>{{ ! empty($cuota->pagada) ? 'Cuota generada' : 'Sin SP hija' }}</td>
						@if ($incluirConcil)
							<td></td><td></td><td></td><td></td><td></td><td></td>
						@endif
					</tr>
				@endforeach
			@endif
		@endforeach
	</tbody>
</table>
