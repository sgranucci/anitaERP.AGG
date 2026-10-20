@php
    $columnasExcel = \App\Support\Stock\ArticuloListadoColumnas::columnasExportables($columnasVisibles ?? []);
    $colspan = max(1, count($columnasExcel));
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
			<td colspan="{{ $colspan }}"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de artículos</h2></td>
		</tr>
		<tr>
			<td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}@if (($totalFilas ?? 0) > 0) — Registros: {{ $totalFilas }}@endif</td>
		</tr>
		@if (trim((string) ($subtitulo ?? '')) !== '')
			<tr>
				<td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
			</tr>
		@endif
	</tbody>
	@include('stock.articulo.partials.tabla_listado_export', [
		'articulos' => $articulos,
		'filasSegmentadas' => $filasSegmentadas ?? null,
		'columnasVisibles' => $columnasExcel,
		'etiquetasColumnas' => $etiquetasColumnas ?? [],
	])
</table>
