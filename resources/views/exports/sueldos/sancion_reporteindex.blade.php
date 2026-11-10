@php
    $columnas = $columnasVisibles ?? [];
    $etiquetas = $etiquetasColumnas ?? [];
    $span = max(1, count($columnas));
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr><td colspan="{{ $span }}" style="height: 52px;">&#160;</td></tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="{{ $span }}"><strong style="font-size: 16pt;">Consultas de sanciones</strong></td>
		</tr>
		<tr>
			<td colspan="{{ $span }}">Generado {{ date('d/m/Y H:i') }}@if (!empty($subtitulo)) — {{ $subtitulo }}@endif</td>
		</tr>
	</tbody>
	<thead>
		<tr>
			@foreach ($columnas as $key)
				<th>{{ $etiquetas[$key] ?? $key }}</th>
			@endforeach
		</tr>
	</thead>
	<tbody>
		@foreach ($datas as $row)
			<tr>
				@foreach ($columnas as $key)
					<td>{{ \App\Support\Sueldos\SancionReporteListadoColumnas::valorCelda($row, $key) }}</td>
				@endforeach
			</tr>
		@endforeach
	</tbody>
</table>
